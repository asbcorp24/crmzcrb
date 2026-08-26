<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.perform');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tasks', [TaskController::class, 'page'])->name('tasks.page');
    Route::get('/ajax/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/ajax/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/ajax/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');

    Route::get('/employees', [EmployeeController::class, 'page'])->name('employees.page');
    Route::get('/ajax/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/ajax/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::patch('/ajax/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');

    Route::get('/departments', [DepartmentController::class, 'page'])->name('departments.page');
    Route::get('/ajax/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/ajax/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::patch('/ajax/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
});
