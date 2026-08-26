<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.perform');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/ajax/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/ajax/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/ajax/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
});
