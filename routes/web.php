<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/ajax/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/ajax/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/ajax/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
});
