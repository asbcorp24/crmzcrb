<?php

use App\Http\Controllers\SuperAdmin\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::patch('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::post('/organizations/{organization}/toggle', [OrganizationController::class, 'toggle'])->name('organizations.toggle');
});
