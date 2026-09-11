<?php

use App\Http\Controllers\AssessmentAnalyticsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->get('/login/organization-info', [LoginController::class, 'organizationInfo'])
    ->name('login.organization-info');

Route::middleware('auth')->group(function () {
    Route::get('/settings', [SettingsController::class, 'page'])->name('settings.page');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/analytics/attestation', [AssessmentAnalyticsController::class, 'attestation'])->name('analytics.attestation');
    Route::get('/analytics/questionnaires', [AssessmentAnalyticsController::class, 'questionnaires'])->name('analytics.questionnaires');
});
