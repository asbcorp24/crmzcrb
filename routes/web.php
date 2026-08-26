<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\StaffingController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskOverdueReasonController;
use App\Http\Controllers\TaskTemplateController;
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
    Route::post('/ajax/tasks/{task}/dashboard-complete', [TaskController::class, 'dashboardComplete'])->name('tasks.dashboard-complete');
    Route::post('/ajax/tasks/{task}/submit-review', [TaskController::class, 'submitReview'])->name('tasks.submit-review');
    Route::post('/ajax/tasks/{task}/accept', [TaskController::class, 'accept'])->name('tasks.accept');
    Route::post('/ajax/tasks/{task}/reject', [TaskController::class, 'reject'])->name('tasks.reject');
    Route::post('/ajax/tasks/{task}/checklist', [TaskController::class, 'addChecklistItem'])->name('tasks.checklist.store');
    Route::patch('/ajax/tasks/{task}/checklist/{item}', [TaskController::class, 'toggleChecklistItem'])->name('tasks.checklist.toggle');
    Route::post('/ajax/tasks/{task}/deadline', [TaskController::class, 'changeDeadline'])->name('tasks.deadline.change');
    Route::post('/ajax/tasks/{task}/overdue-reason', [TaskOverdueReasonController::class, 'store'])->name('tasks.overdue-reason.store');
    Route::post('/ajax/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
    Route::get('/attachments/{attachment}/download', [TaskAttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/ajax/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('/task-templates', [TaskTemplateController::class, 'page'])->name('task-templates.page');
    Route::get('/ajax/task-templates', [TaskTemplateController::class, 'index'])->name('task-templates.index');
    Route::post('/ajax/task-templates', [TaskTemplateController::class, 'store'])->name('task-templates.store');
    Route::patch('/ajax/task-templates/{template}', [TaskTemplateController::class, 'update'])->name('task-templates.update');
    Route::post('/ajax/task-templates/{template}/toggle', [TaskTemplateController::class, 'toggle'])->name('task-templates.toggle');
    Route::post('/ajax/task-templates/{template}/create-task', [TaskTemplateController::class, 'createTask'])->name('task-templates.create-task');

    Route::get('/plans', [PlanController::class, 'page'])->name('plans.page');
    Route::get('/ajax/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('/ajax/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::patch('/ajax/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::post('/ajax/plans/{plan}/tasks', [PlanController::class, 'addTask'])->name('plans.tasks.store');

    Route::get('/employees', [EmployeeController::class, 'page'])->name('employees.page');
    Route::get('/employees/{employee}', [EmployeeController::class, 'profile'])->name('employees.profile');
    Route::get('/ajax/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/ajax/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::patch('/ajax/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');

    Route::get('/departments', [DepartmentController::class, 'page'])->name('departments.page');
    Route::get('/ajax/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/ajax/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::patch('/ajax/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');

    Route::get('/staffing', [StaffingController::class, 'page'])->name('staffing.page');
    Route::get('/ajax/staffing/positions', [StaffingController::class, 'positions'])->name('staffing.positions');
    Route::post('/ajax/staffing/positions', [StaffingController::class, 'storePosition'])->name('staffing.positions.store');
    Route::patch('/ajax/staffing/positions/{position}', [StaffingController::class, 'updatePosition'])->name('staffing.positions.update');
    Route::get('/ajax/staffing/rows', [StaffingController::class, 'rows'])->name('staffing.rows');
    Route::post('/ajax/staffing/rows', [StaffingController::class, 'storeRow'])->name('staffing.rows.store');
    Route::patch('/ajax/staffing/rows/{staffingPosition}', [StaffingController::class, 'updateRow'])->name('staffing.rows.update');
    Route::get('/ajax/employees/{employee}/assignments', [StaffingController::class, 'assignments'])->name('staffing.assignments');
    Route::post('/ajax/staffing/assignments', [StaffingController::class, 'assign'])->name('staffing.assignments.store');
    Route::post('/ajax/staffing/assignments/{assignment}/end', [StaffingController::class, 'endAssignment'])->name('staffing.assignments.end');

    Route::get('/control', [ControlController::class, 'page'])->name('control.page');
    Route::get('/ajax/control', [ControlController::class, 'data'])->name('control.data');
});
