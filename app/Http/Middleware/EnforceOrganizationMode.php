<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnforceOrganizationMode
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) return $next($request);

        $user = auth()->user();
        $route = optional($request->route())->getName();

        if ($user->isSuperAdmin()) {
            if (!in_array($route, ['logout'], true) && !str_starts_with((string)$route, 'superadmin.')) {
                return redirect()->route('superadmin.organizations.index');
            }
            return $next($request);
        }

        if (str_starts_with((string)$route, 'superadmin.')) abort(403);

        $organization = $user->organization;
        if (!$user->organization_id || !$organization?->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email'=>'Организация отключена или не назначена.']);
        }

        if ($organization->timezone) {
            config(['app.timezone'=>$organization->timezone]);
            date_default_timezone_set($organization->timezone);
        }

        $response = $next($request);
        $contentType = (string)$response->headers->get('Content-Type');
        if (!str_contains($contentType, 'text/html') || !method_exists($response,'getContent')) return $response;

        $html = $response->getContent();
        if (!is_string($html) || $html==='') return $response;

        $displayName = e($organization->display_name);
        $primary = $this->safeColor($organization->primary_color, '#0d6efd');
        $secondary = $this->safeColor($organization->secondary_color, '#6c757d');
        [$pr,$pg,$pb] = $this->hexRgb($primary);
        [$sr,$sg,$sb] = $this->hexRgb($secondary);
        $icon = $organization->icon_path ?: '/pwa-icon.svg';

        $html = str_replace('CRM ЗЦРБ', $displayName, $html);
        $html = str_replace('/pwa-icon.svg', e($icon), $html);
        $branding = '<style id="organization-branding">:root{--bs-primary:'.$primary.';--bs-primary-rgb:'.$pr.','.$pg.','.$pb.';--bs-secondary:'.$secondary.';--bs-secondary-rgb:'.$sr.','.$sg.','.$sb.'}.text-primary{color:'.$primary.'!important}.bg-primary{background-color:'.$primary.'!important}.btn-primary{--bs-btn-bg:'.$primary.';--bs-btn-border-color:'.$primary.';--bs-btn-hover-bg:'.$primary.';--bs-btn-hover-border-color:'.$primary.'}.progress-bar{background-color:'.$primary.'}.sidebar .nav-link.active,.sidebar .nav-link:hover{color:'.$primary.'!important}.app-mark{background:'.$primary.'!important}</style>';
        $html = str_replace('</head>', $branding.'</head>', $html);
        $response->setContent($html);
        return $response;
    }

    private function safeColor(?string $value, string $fallback): string
    {
        return $value && preg_match('/^#[0-9A-Fa-f]{6}$/',$value) ? $value : $fallback;
    }

    private function hexRgb(string $hex): array
    {
        $hex=ltrim($hex,'#');
        return [hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))];
    }
}
