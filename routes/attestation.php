<?php

use App\Http\Controllers\AttestationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/attestation/{campaign}/commission-scores', [AttestationController::class, 'saveCommissionScores'])
        ->name('attestation.commission-scores.save');
});
