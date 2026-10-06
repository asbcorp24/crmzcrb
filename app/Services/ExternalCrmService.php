<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class ExternalCrmService
{
    public function configured(Organization $organization): bool
    {
        $settings = $organization->settings ?: [];

        return !empty($settings['external_crm_url'])
            && !empty($settings['external_crm_token']);
    }

    public function health(Organization $organization): array
    {
        return $this->get($organization, '/api/crm/v1/health');
    }

    public function workshops(Organization $organization): array
    {
        return $this->all($organization, '/api/crm/v1/workshops');
    }

    public function taskTypes(Organization $organization): array
    {
        return $this->all($organization, '/api/crm/v1/task-types');
    }

    public function users(Organization $organization, bool $includeInactive = false): array
    {
        return $this->all($organization, '/api/crm/v1/users', [
            'include_inactive' => $includeInactive ? 'true' : 'false',
        ]);
    }

    public function plansReport(Organization $organization, string $month): array
    {
        return $this->get($organization, '/api/crm/v1/plans/report', ['month' => $month]);
    }

    public function plansAnalytics(Organization $organization, string $month): array
    {
        return $this->get($organization, '/api/crm/v1/plans/analytics', ['month' => $month]);
    }

    public function createTask(Organization $organization, array $payload): array
    {
        return $this->send($organization, 'POST', '/api/crm/v1/tasks', $payload);
    }

    public function updateTask(Organization $organization, int $externalTaskId, array $payload): array
    {
        return $this->send($organization, 'PATCH', '/api/crm/v1/tasks/'.$externalTaskId, $payload);
    }

    public function taskSummaries(Organization $organization, array $externalTaskIds): array
    {
        $ids = collect($externalTaskIds)
            ->map(fn ($id) => (int)$id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (!$ids) {
            return [];
        }

        $response = $this->get($organization, '/api/crm/v1/tasks/summaries', [
            'ids' => implode(',', $ids),
        ]);

        return isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
    }

    public function taskDetails(Organization $organization, int $externalTaskId): array
    {
        return $this->get($organization, '/api/crm/v1/tasks/'.$externalTaskId);
    }

    public function taskLogs(Organization $organization, int $externalTaskId): array
    {
        return $this->get($organization, '/api/crm/v1/tasks/'.$externalTaskId.'/logs');
    }

    public function addTaskLog(Organization $organization, int $externalTaskId, array $payload): array
    {
        return $this->send($organization, 'POST', '/api/crm/v1/tasks/'.$externalTaskId.'/logs', $payload);
    }

    public function downloadTaskDocument(Organization $organization, int $externalTaskId, int $documentId): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('На сервере PHP не включено расширение cURL.');
        }

        $settings = $organization->settings ?: [];
        $encrypted = (string)($settings['external_crm_token'] ?? '');
        if ($encrypted === '') {
            throw new RuntimeException('API-токен внешней CRM не настроен.');
        }

        try {
            $token = Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            throw new RuntimeException('Не удалось расшифровать API-токен внешней CRM.');
        }

        $headers = [];
        $ch = curl_init($this->url(
            $organization,
            '/api/crm/v1/tasks/'.$externalTaskId.'/documents/'.$documentId.'/download'
        ));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/octet-stream, application/json',
                'Authorization: Bearer '.$token,
            ],
            CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$headers): int {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            },
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Ошибка загрузки документа из внешней CRM: '.$error);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string)(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream');
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            $json = json_decode((string)$body, true);
            $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? '') : '';
            throw new RuntimeException('Внешняя CRM: '.($message ?: 'HTTP '.$status));
        }

        $filename = 'document-'.$documentId;
        $disposition = $headers['content-disposition'] ?? '';
        if (preg_match('/filename\*=UTF-8\'\'([^;]+)/i', $disposition, $m)) {
            $filename = rawurldecode($m[1]);
        } elseif (preg_match('/filename="?([^";]+)"?/i', $disposition, $m)) {
            $filename = $m[1];
        }

        return [
            'body' => (string)$body,
            'content_type' => $contentType,
            'filename' => $filename,
        ];
    }

    public function get(Organization $organization, string $path, array $query = []): array
    {
        $url = $this->url($organization, $path);
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        return $this->request($organization, 'GET', $url);
    }

    private function all(Organization $organization, string $path, array $query = []): array
    {
        $offset = 0;
        $rows = [];

        do {
            $page = $this->get($organization, $path, array_merge($query, [
                'limit' => 500,
                'offset' => $offset,
            ]));

            $chunk = isset($page['data']) && is_array($page['data']) ? $page['data'] : [];
            $rows = array_merge($rows, $chunk);
            $pagination = isset($page['pagination']) && is_array($page['pagination']) ? $page['pagination'] : [];
            $offset += count($chunk);
            $total = (int)($pagination['total'] ?? count($rows));
        } while (!empty($chunk) && $offset < $total);

        return $rows;
    }

    private function send(Organization $organization, string $method, string $path, array $payload): array
    {
        return $this->request(
            $organization,
            $method,
            $this->url($organization, $path),
            $payload
        );
    }

    private function request(Organization $organization, string $method, string $url, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('На сервере PHP не включено расширение cURL.');
        }

        $settings = $organization->settings ?: [];
        $encrypted = (string)($settings['external_crm_token'] ?? '');

        if ($encrypted === '') {
            throw new RuntimeException('API-токен внешней CRM не настроен.');
        }

        try {
            $token = Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            throw new RuntimeException('Не удалось расшифровать API-токен внешней CRM.');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer '.$token,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Не удалось инициализировать cURL.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];

        if ($payload !== null) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Не удалось сформировать JSON для внешней CRM.');
            }
            $options[CURLOPT_POSTFIELDS] = $json;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);

        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Ошибка соединения с внешней CRM: '.$error);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $json = json_decode($body, true);

        return $this->response($status, is_array($json) ? $json : null, (string)$body);
    }

    private function url(Organization $organization, string $path): string
    {
        $settings = $organization->settings ?: [];
        $base = rtrim((string)($settings['external_crm_url'] ?? ''), '/');

        if ($base === '') {
            throw new RuntimeException('Адрес внешней CRM не настроен.');
        }

        return $base.'/'.ltrim($path, '/');
    }

    private function response(int $status, $json, string $body): array
    {
        if ($status >= 200 && $status < 300) {
            return is_array($json) ? $json : [];
        }

        $message = is_array($json)
            ? (string)($json['message'] ?? $json['error'] ?? '')
            : '';

        if ($message === '') {
            $message = trim($body) ?: 'HTTP '.$status;
        }

        throw new RuntimeException('Внешняя CRM: '.$message.' (HTTP '.$status.')');
    }
}
