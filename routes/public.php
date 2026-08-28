<?php

use App\Http\Controllers\TenantManifestController;
use Illuminate\Support\Facades\Route;

Route::get('/tenant-manifest.webmanifest', [TenantManifestController::class, 'show'])->name('tenant.manifest');
