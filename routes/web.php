<?php

/**
 * Web Routes
 *
 * Here is where you can register web routes for your application.
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;

// Demo interface routes
Route::get('/', [HomeController::class, 'index']);
Route::get('/list-tasks', [HomeController::class, 'listTasks']);

// Demo API routes with rate limiting
Route::middleware(['throttle:60,1'])->group(
    function () {
        Route::post('/demo/upload', [HomeController::class, 'upload'])
            ->middleware('image.rate.limit');
        Route::get('/demo/status/{taskId}', [HomeController::class, 'status']);
    }
);

// Download routes (from existing API controller)
Route::get(
    '/download/{taskId}',
    [App\Http\Controllers\OptimizeController::class, 'download']
)->name('demo.download');
Route::get(
    '/download/{taskId}/webp',
    [App\Http\Controllers\OptimizeController::class, 'downloadWebp']
)->name('demo.download.webp');

// Add this route for health checks
Route::get(
    '/health',
    function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now(),
            'app' => config('app.name'),
            'environment' => app()->environment(),
        ]);
    }
);
