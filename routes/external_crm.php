<?php

use App\Http\Controllers\ExternalCrmController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/external-crm', [ExternalCrmController::class, 'page'])->name('external-crm.page');
    Route::patch('/external-crm/connection', [ExternalCrmController::class, 'saveConnection'])->name('external-crm.connection');
    Route::post('/ajax/external-crm/test', [ExternalCrmController::class, 'test'])->name('external-crm.test');
    Route::patch('/external-crm/mappings', [ExternalCrmController::class, 'saveMappings'])->name('external-crm.mappings');
    Route::post('/ajax/external-crm/tasks/{task}/sync', [ExternalCrmController::class, 'syncTask'])->name('external-crm.tasks.sync');
});
