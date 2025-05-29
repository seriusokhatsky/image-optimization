<?php

use App\Http\Controllers\OptimizeController;
use Illuminate\Support\Facades\Route;

// New async API endpoints
Route::prefix('optimize')->group(function () {
    Route::post('/submit', [OptimizeController::class, 'submit']);
    Route::get('/status/{taskId}', [OptimizeController::class, 'status']);
    Route::get('/download/{taskId}', [OptimizeController::class, 'download'])->name('optimize.download');
});

// Legacy endpoint (deprecated)
Route::post('/optimize', [OptimizeController::class, 'optimize']); 