<?php

namespace App\Providers;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/';

    public function boot(): void
    {
        $router = $this->app['router'];

        // Регистрируем aliases явно. Это делает портал устойчивым к
        // различиям skeleton Laravel 9 / обновлённым vendor-пакетам.
        $router->aliasMiddleware('auth', Authenticate::class);
        $router->aliasMiddleware('auth.basic', AuthenticateWithBasicAuth::class);
        $router->aliasMiddleware('cache.headers', SetCacheHeaders::class);
        $router->aliasMiddleware('can', Authorize::class);
        $router->aliasMiddleware('guest', RedirectIfAuthenticated::class);
        $router->aliasMiddleware('password.confirm', RequirePassword::class);
        $router->aliasMiddleware('signed', ValidateSignature::class);
        $router->aliasMiddleware('throttle', ThrottleRequests::class);
        $router->aliasMiddleware('verified', EnsureEmailIsVerified::class);
        $router->aliasMiddleware('bindings', SubstituteBindings::class);
        $router->aliasMiddleware('superadmin', SuperAdminMiddleware::class);

        RateLimiter::for('web-login', function (Request $request) {
            return Limit::perMinute(10)->by(
                mb_strtolower((string)$request->input('login')).'|'.$request->ip()
            );
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        $this->routes(function () {
            Route::middleware('web')->group(base_path('routes/web.php'));
        });
    }
}
