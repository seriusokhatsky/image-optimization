<?php

use App\Http\Controllers\OptimizeController;
use Illuminate\Support\Facades\Route;

// Quota management endpoints (no quota validation required)
Route::prefix('optimize')->group(function () {
    Route::post('/refresh-quota', [OptimizeController::class, 'refreshQuota']);
    Route::post('/reset-usage', [OptimizeController::class, 'resetUsage']);
});

// Quota info endpoints (fetch quota only, no validation)
Route::prefix('optimize')->middleware('quota.fetch')->group(function () {
    Route::get('/quota', [OptimizeController::class, 'quota']);
    Route::get('/status/{taskId}', [OptimizeController::class, 'status']);
    Route::get('/download/{taskId}', [OptimizeController::class, 'download'])->name('optimize.download');
    Route::get('/download/{taskId}/webp', [OptimizeController::class, 'downloadWebp'])->name('optimize.download.webp');
});

// Quota-protected endpoints (fetch quota AND validate availability)
Route::prefix('optimize')->middleware('quota.validate')->group(function () {
    Route::post('/submit', [OptimizeController::class, 'submit']);
}); 