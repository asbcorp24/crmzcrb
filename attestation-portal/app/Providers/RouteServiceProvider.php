<?php
namespace App\Providers;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/';
    public function boot(): void
    {
        RateLimiter::for('web-login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        $this->routes(function () {
            Route::middleware('web')->group(base_path('routes/web.php'));
        });
    }
}
