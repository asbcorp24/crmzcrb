<?php

use App\Http\Controllers\TaskDetailsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/ajax/task-details/options', [TaskDetailsController::class, 'options'])->name('task-details.options');
    Route::get('/ajax/task-details/batch', [TaskDetailsController::class, 'batch'])->name('task-details.batch');
    Route::get('/ajax/tasks/{task}/details', [TaskDetailsController::class, 'show'])->name('task-details.show');
    Route::patch('/ajax/tasks/{task}/details', [TaskDetailsController::class, 'update'])->name('task-details.update');
    Route::post('/ajax/tasks/{task}/links', [TaskDetailsController::class, 'storeLink'])->name('task-links.store');
    Route::delete('/ajax/tasks/{task}/links/{link}', [TaskDetailsController::class, 'destroyLink'])->name('task-links.destroy');
});
