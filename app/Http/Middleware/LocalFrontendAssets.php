<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LocalFrontendAssets
{
    private static ?array $manifest = null;
    private static ?int $mtime = null;

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (!method_exists($response, 'getContent')) return $response;

        $type = (string)$response->headers->get('Content-Type');
        if (!str_contains($type, 'text/html')) return $response;

        $manifestPath = public_path('vendor/external/manifest.json');
        if (!is_file($manifestPath)) return $response;

        $mtime = filemtime($manifestPath) ?: 0;
        if (self::$manifest === null || self::$mtime !== $mtime) {
            $decoded = json_decode(File::get($manifestPath), true);
            self::$manifest = is_array($decoded) ? $decoded : [];
            self::$mtime = $mtime;
        }

        if (!self::$manifest) return $response;
        $html = $response->getContent();
        if (!is_string($html) || $html === '') return $response;

        $response->setContent(str_replace(array_keys(self::$manifest), array_values(self::$manifest), $html));
        return $response;
    }
}
