<?php

use AIToolkit\AIToolkit\Http\Controllers\AiHealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Toolkit Routes
|--------------------------------------------------------------------------
*/

Route::prefix('ai')->middleware(['api'])->group(function () {

    // Health check endpoints
    Route::get('/health', [AiHealthController::class, 'check']);
    Route::get('/health/live', [AiHealthController::class, 'live']);
    Route::get('/health/ready', [AiHealthController::class, 'ready']);

    // Metrics endpoints
    Route::get('/metrics', [AiHealthController::class, 'metrics']);
    Route::get('/performance', [AiHealthController::class, 'performance']);
    Route::get('/export', [AiHealthController::class, 'export']);
});
