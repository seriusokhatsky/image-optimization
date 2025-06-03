<?php

/**
 * Home Controller for displaying the main page with optimization tasks.
 */

namespace App\Http\Controllers;

use App\Models\OptimizationTask;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller for handling home page requests and displaying optimization tasks.
 */
class HomeController extends Controller
{
    /**
     * Display the home page with latest tasks.
     *
     * @return View
     */
    public function index(): View
    {
        // Get the latest 20 optimization tasks
        $latestTasks = OptimizationTask::latest('created_at')
            ->take(20)
            ->get();

        return view('welcome', compact('latestTasks'));
    }
} 