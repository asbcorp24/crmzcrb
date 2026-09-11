<?php

use App\Http\Controllers\AssessmentAnalyticsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->get('/login/organization-info', [LoginController::class, 'organizationInfo'])
    ->name('login.organization-info');

Route::middleware('auth')->group(function () {
    Route::get('/settings', [SettingsController::class, 'page'])->name('settings.page');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/my-settings', [UserSettingsController::class, 'page'])->name('user-settings.page');
    Route::patch('/my-settings', [UserSettingsController::class, 'update'])->name('user-settings.update');
    Route::get('/ajax/user-theme', [UserSettingsController::class, 'theme'])->name('user-settings.theme');

    Route::get('/analytics/attestation', [AssessmentAnalyticsController::class, 'attestation'])->name('analytics.attestation');
    Route::get('/analytics/questionnaires', [AssessmentAnalyticsController::class, 'questionnaires'])->name('analytics.questionnaires');
});
