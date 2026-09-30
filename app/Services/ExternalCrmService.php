<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
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

    public function createTask(Organization $organization, array $payload): array
    {
        return $this->send($organization, 'POST', '/api/crm/v1/tasks', $payload);
    }

    public function updateTask(Organization $organization, int $externalTaskId, array $payload): array
    {
        return $this->send($organization, 'PATCH', '/api/crm/v1/tasks/'.$externalTaskId, $payload);
    }

    public function get(Organization $organization, string $path, array $query = []): array
    {
        $response = $this->client($organization)->get($this->url($organization, $path), $query);

        return $this->response($response->status(), $response->json(), $response->body());
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
        $response = $this->client($organization)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->send($method, $this->url($organization, $path), ['json' => $payload]);

        return $this->response($response->status(), $response->json(), $response->body());
    }

    private function client(Organization $organization): PendingRequest
    {
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

        return Http::acceptJson()
            ->withToken($token)
            ->connectTimeout(5)
            ->timeout(15);
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
