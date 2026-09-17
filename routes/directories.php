<?php

use App\Http\Controllers\ReferenceDirectoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/directories', [ReferenceDirectoryController::class, 'page'])->name('directories.page');
    Route::get('/ajax/directories', [ReferenceDirectoryController::class, 'index'])->name('directories.index');
    Route::post('/ajax/directories', [ReferenceDirectoryController::class, 'store'])->name('directories.store');
    Route::patch('/ajax/directories/{referenceItem}', [ReferenceDirectoryController::class, 'update'])->name('directories.update');
    Route::patch('/ajax/directories/{referenceItem}/toggle', [ReferenceDirectoryController::class, 'toggle'])->name('directories.toggle');
});
