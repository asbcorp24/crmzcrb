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
        } elseif (str_starts_with((string)$route, 'superadmin.')) {
            abort(403);
        }

        if (!$user->isSuperAdmin() && (!$user->organization_id || !$user->organization?->is_active)) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email'=>'Организация отключена или не назначена.']);
        }

        return $next($request);
    }
}
