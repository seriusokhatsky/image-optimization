<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;

// Demo interface routes
Route::get('/', [HomeController::class, 'index']);

// Demo API routes with rate limiting
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/demo/upload', [HomeController::class, 'upload'])
        ->middleware('image.rate.limit');
    Route::get('/demo/status/{taskId}', [HomeController::class, 'status']);
});

// Download routes (from existing API controller)
Route::get('/download/{taskId}', [App\Http\Controllers\OptimizeController::class, 'download'])->name('demo.download');
Route::get('/download/{taskId}/webp', [App\Http\Controllers\OptimizeController::class, 'downloadWebp'])->name('demo.download.webp');
