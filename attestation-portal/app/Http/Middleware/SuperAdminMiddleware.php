<?php
namespace App\Http\Middleware;
use Closure;
class SuperAdminMiddleware
{
    public function handle($request, Closure $next)
    {
        abort_unless($request->user() && $request->user()->isSuperAdmin(), 403);
        return $next($request);
    }
}
