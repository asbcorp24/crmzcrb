<?php

use App\Http\Controllers\ProductionMeetingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/production-meetings', [ProductionMeetingController::class, 'page'])->name('production-meetings.page');
    Route::get('/production-meetings/{productionMeeting}/print', [ProductionMeetingController::class, 'print'])->name('production-meetings.print');

    Route::get('/ajax/production-meetings', [ProductionMeetingController::class, 'index'])->name('production-meetings.index');
    Route::post('/ajax/production-meetings', [ProductionMeetingController::class, 'store'])->name('production-meetings.store');
    Route::get('/ajax/production-meetings/{productionMeeting}', [ProductionMeetingController::class, 'show'])->name('production-meetings.show');
    Route::patch('/ajax/production-meetings/{productionMeeting}', [ProductionMeetingController::class, 'update'])->name('production-meetings.update');
    Route::post('/ajax/production-meetings/{productionMeeting}/items', [ProductionMeetingController::class, 'storeItem'])->name('production-meetings.items.store');
    Route::patch('/ajax/production-meetings/{productionMeeting}/items/{item}', [ProductionMeetingController::class, 'updateItem'])->name('production-meetings.items.update');
    Route::delete('/ajax/production-meetings/{productionMeeting}/items/{item}', [ProductionMeetingController::class, 'destroyItem'])->name('production-meetings.items.destroy');
    Route::post('/ajax/production-meetings/{productionMeeting}/generate-protocol', [ProductionMeetingController::class, 'generateProtocol'])->name('production-meetings.generate-protocol');
});
