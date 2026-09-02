<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DownloadFrontendAssets extends Command
{
    protected $signature = 'crm:vendor-assets {--force : Перекачать уже существующие файлы}';
    protected $description = 'Скачивает внешние CSS/JS/шрифты в public/vendor/external для работы CRM без интернета';

    private array $manifest = [];
    private array $visited = [];

    public function handle(): int
    {
        $root = public_path('vendor/external');
        File::ensureDirectoryExists($root);

        $urls = $this->discoverExternalAssets();
        if ($urls->isEmpty()) {
            $this->warn('Внешние библиотеки в шаблонах не найдены.');
        }

        $this->info('Найдено внешних ресурсов: '.$urls->count());
        foreach ($urls as $url) {
            try {
                $this->download($url, true);
            } catch (\Throwable $e) {
                $this->error($url.' -> '.$e->getMessage());
                return self::FAILURE;
            }
        }

        ksort($this->manifest);
        File::put($root.'/manifest.json', json_encode($this->manifest, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $this->info('Готово. Локальных ресурсов: '.count($this->manifest));
        $this->line('Manifest: public/vendor/external/manifest.json');
        return self::SUCCESS;
    }

    private function discoverExternalAssets()
    {
        $files = collect();
        $viewRoot = resource_path('views');
        if (is_dir($viewRoot)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewRoot));
            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) $files->push($file->getPathname());
            }
        }
        foreach ([public_path('offline.html'), public_path('pwa-runtime.js'), public_path('sw.js')] as $path) {
            if (is_file($path)) $files->push($path);
        }

        $urls = collect();
        foreach ($files as $path) {
            $text = File::get($path);
            preg_match_all('/(?:src|href)=["\'](https?:\/\/[^"\']+)["\']/i', $text, $m);
            foreach ($m[1] ?? [] as $url) {
                if ($this->isAssetUrl($url)) $urls->push(html_entity_decode($url));
            }
        }
        return $urls->unique()->values();
    }

    private function isAssetUrl(string $url): bool
    {
        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        return preg_match('/\.(css|js|mjs|woff2?|ttf|otf|svg)(?:$|\?)/', $path) === 1;
    }

    private function download(string $url, bool $parseCss = false): string
    {
        if (isset($this->visited[$url])) return $this->visited[$url];

        $localUrl = $this->localUrlFor($url);
        $localPath = public_path(ltrim($localUrl, '/'));
        $this->visited[$url] = $localUrl;
        $this->manifest[$url] = $localUrl;

        if (!is_file($localPath) || $this->option('force')) {
            File::ensureDirectoryExists(dirname($localPath));
            File::put($localPath, $this->fetchRemote($url));
            $this->line('  + '.$url);
        } else {
            $this->line('  = '.$url);
        }

        if ($parseCss && str_ends_with(strtolower((string)parse_url($url, PHP_URL_PATH)), '.css')) {
            $this->vendorCssDependencies($url, $localPath);
        }

        return $localUrl;
    }

    private function fetchRemote(string $url): string
    {
        // Не используем Laravel Http/Guzzle: команда должна работать даже в
        // минимальной установке OpenServer, где guzzlehttp/guzzle отсутствует.
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 8,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_USERAGENT => 'CRM-ZCRB-Asset-Vendor/1.1',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING => '',
            ]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($body === false || $errno) {
                throw new \RuntimeException('cURL: '.($error ?: 'ошибка '.$errno));
            }
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException('HTTP '.$status);
            }
            return $body;
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            throw new \RuntimeException('Нет cURL и выключен allow_url_fopen. Включите расширение curl в OpenServer/PHP.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 60,
                'follow_location' => 1,
                'max_redirects' => 8,
                'header' => "User-Agent: CRM-ZCRB-Asset-Vendor/1.1\r\nAccept: */*\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $m)) $status = (int)$m[1];
        }
        if ($body === false) throw new \RuntimeException('Не удалось скачать ресурс через PHP stream');
        if ($status && ($status < 200 || $status >= 300)) throw new \RuntimeException('HTTP '.$status);
        return $body;
    }

    private function vendorCssDependencies(string $cssUrl, string $localPath): void
    {
        $css = File::get($localPath);
        preg_match_all('/url\(([^)]+)\)/i', $css, $m);
        foreach ($m[1] ?? [] as $raw) {
            $ref = trim($raw, " \t\n\r\0\x0B\"'");
            if ($ref === '' || str_starts_with($ref, 'data:') || str_starts_with($ref, '#')) continue;
            $absolute = $this->resolveUrl($cssUrl, $ref);
            if (!$absolute) continue;
            $this->download($absolute, false);
        }
    }

    private function localUrlFor(string $url): string
    {
        $p = parse_url($url);
        $host = preg_replace('/[^a-zA-Z0-9._-]/', '_', $p['host'] ?? 'external');
        $path = $p['path'] ?? '/asset';
        $path = preg_replace('#(^|/)\.\.?(/|$)#', '/', $path);
        $path = ltrim($path, '/');
        if ($path === '' || str_ends_with($path, '/')) $path .= 'index';
        if (!empty($p['query'])) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $base = $ext ? substr($path, 0, -(strlen($ext)+1)) : $path;
            $path = $base.'-'.substr(sha1($p['query']),0,10).($ext?'.'.$ext:'');
        }
        return '/vendor/external/'.$host.'/'.$path;
    }

    private function resolveUrl(string $base, string $ref): ?string
    {
        if (preg_match('#^https?://#i', $ref)) return $ref;
        $p = parse_url($base);
        if (empty($p['scheme']) || empty($p['host'])) return null;
        if (str_starts_with($ref, '//')) return $p['scheme'].':'.$ref;
        if (str_starts_with($ref, '/')) return $p['scheme'].'://'.$p['host'].$ref;

        $dir = dirname($p['path'] ?? '/');
        $combined = $dir.'/'.$ref;
        $parts = [];
        foreach (explode('/', $combined) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') array_pop($parts); else $parts[] = $part;
        }
        return $p['scheme'].'://'.$p['host'].'/'.implode('/', $parts);
    }
}
