<?php

use App\Http\Controllers\OptimizeController;
use Illuminate\Support\Facades\Route;

Route::post('/optimize', [OptimizeController::class, 'optimize']); 