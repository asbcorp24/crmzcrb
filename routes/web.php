<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.perform');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/ajax/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/ajax/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/ajax/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/calendar', [CalendarController::class, 'page'])->name('calendar.page');
    Route::get('/ajax/calendar/events', [CalendarController::class, 'events'])->name('calendar.events');

    Route::get('/tasks', [TaskController::class, 'page'])->name('tasks.page');
    Route::get('/ajax/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/ajax/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::post('/ajax/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/ajax/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('/ajax/tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comments.store');
    Route::post('/ajax/tasks/{task}/submit-review', [TaskController::class, 'submitReview'])->name('tasks.submit-review');
    Route::post('/ajax/tasks/{task}/accept', [TaskController::class, 'accept'])->name('tasks.accept');
    Route::post('/ajax/tasks/{task}/reject', [TaskController::class, 'reject'])->name('tasks.reject');

    Route::get('/plans', [PlanController::class, 'page'])->name('plans.page');
    Route::get('/ajax/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('/ajax/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::patch('/ajax/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::post('/ajax/plans/{plan}/tasks', [PlanController::class, 'addTask'])->name('plans.tasks.store');

    Route::get('/employees', [EmployeeController::class, 'page'])->name('employees.page');
    Route::get('/ajax/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/ajax/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::patch('/ajax/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');

    Route::get('/departments', [DepartmentController::class, 'page'])->name('departments.page');
    Route::get('/ajax/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/ajax/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::patch('/ajax/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');

    Route::get('/control', [ControlController::class, 'page'])->name('control.page');
    Route::get('/ajax/control', [ControlController::class, 'data'])->name('control.data');
});
