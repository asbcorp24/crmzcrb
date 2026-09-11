<?php

use App\Http\Controllers\DashboardAnalyticsController;
use App\Http\Controllers\OrganizationAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/ajax/analytics/dashboard', [DashboardAnalyticsController::class, 'data'])->name('analytics.dashboard-data');

    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [OrganizationAnalyticsController::class, 'overview'])->name('overview');
        Route::get('/tasks', [OrganizationAnalyticsController::class, 'tasks'])->name('tasks');
        Route::get('/plans', [OrganizationAnalyticsController::class, 'plans'])->name('plans');
        Route::get('/employees', [OrganizationAnalyticsController::class, 'employees'])->name('employees');
        Route::get('/departments', [OrganizationAnalyticsController::class, 'departments'])->name('departments');
    });
});
