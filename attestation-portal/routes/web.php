<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AttestationController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function(){
    Route::get('/login',[AuthController::class,'loginPage'])->name('login');
    Route::post('/login',[AuthController::class,'login'])->middleware('throttle:web-login')->name('login.submit');
});

Route::middleware('auth')->group(function(){
    Route::post('/logout',[AuthController::class,'logout'])->name('logout');
    Route::get('/',[DashboardController::class,'index'])->name('dashboard');

    Route::get('/tests/{test}',[AttemptController::class,'show'])->name('attempt.show');
    Route::post('/tests/{test}/attempts/{attempt}',[AttemptController::class,'submit'])->name('attempt.submit');
    Route::get('/attempts/{attempt}/result',[AttemptController::class,'result'])->name('attempt.result');

    Route::get('/attestation',[AttestationController::class,'index'])->name('attestation.index');
    Route::post('/attestation/{campaign}/peer',[AttestationController::class,'savePeer'])->name('attestation.peer');
    Route::post('/attestation/{campaign}/commission',[AttestationController::class,'saveCommission'])->name('attestation.commission');

    Route::prefix('admin')->name('admin.')->middleware('superadmin')->group(function(){
        Route::get('/',[AdminController::class,'dashboard'])->name('dashboard');

        Route::get('/workshops',[AdminController::class,'workshops'])->name('workshops');
        Route::post('/workshops',[AdminController::class,'storeWorkshop'])->name('workshops.store');
        Route::patch('/workshops/{workshop}',[AdminController::class,'updateWorkshop'])->name('workshops.update');

        Route::get('/users',[AdminController::class,'users'])->name('users');
        Route::post('/users',[AdminController::class,'storeUser'])->name('users.store');
        Route::patch('/users/{user}',[AdminController::class,'updateUser'])->name('users.update');
        Route::post('/users/{user}/password',[AdminController::class,'resetPassword'])->name('users.password');

        Route::get('/tests',[TestController::class,'index'])->name('tests.index');
        Route::post('/tests',[TestController::class,'store'])->name('tests.store');
        Route::get('/tests/{test}/edit',[TestController::class,'edit'])->name('tests.edit');
        Route::patch('/tests/{test}',[TestController::class,'update'])->name('tests.update');
        Route::post('/tests/{test}/questions',[TestController::class,'addQuestion'])->name('tests.questions.store');
        Route::delete('/tests/{test}/questions/{question}',[TestController::class,'deleteQuestion'])->name('tests.questions.destroy');

        Route::get('/attestation',[AttestationController::class,'admin'])->name('attestation');
        Route::post('/attestation',[AttestationController::class,'storeCampaign'])->name('attestation.store');

        Route::get('/analytics',[AnalyticsController::class,'index'])->name('analytics');
        Route::get('/analytics/attestation',[AnalyticsController::class,'attestation'])->name('analytics.attestation');
    });
});
