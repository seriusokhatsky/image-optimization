<?php

use App\Http\Controllers\OptimizeController;
use App\Http\Middleware\QuotaMiddleware;
use Illuminate\Support\Facades\Route;

// Quota management endpoints (no quota validation required)
Route::prefix('optimize')->group(function () {
    Route::get('/quota', [OptimizeController::class, 'quota']);
    Route::post('/refresh-quota', [OptimizeController::class, 'refreshQuota']);
    Route::post('/reset-usage', [OptimizeController::class, 'resetUsage']);
});

// Quota-protected optimization endpoints (require available quota)
Route::prefix('optimize')->middleware(QuotaMiddleware::class)->group(function () {
    Route::post('/submit', [OptimizeController::class, 'submit']);
    Route::get('/status/{taskId}', [OptimizeController::class, 'status']);
    Route::get('/download/{taskId}', [OptimizeController::class, 'download'])->name('optimize.download');
    Route::get('/download/{taskId}/webp', [OptimizeController::class, 'downloadWebp'])->name('optimize.download.webp');
}); 