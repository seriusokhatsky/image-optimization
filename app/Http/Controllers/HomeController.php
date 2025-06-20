<?php

/**
 * Home Controller for the image optimization demo interface.
 */

namespace App\Http\Controllers;

use App\Helpers\FormatHelper;
use App\Jobs\OptimizeFileJob;
use App\Models\OptimizationTask;
use App\Services\OptimizationLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controller for handling the demo web interface for image optimization.
 */
class HomeController extends Controller
{
    public function __construct(
        private OptimizationLogger $logger
    ) {}

    /**
     * Display the demo interface.
     *
     * @return View
     */
    public function index(): View
    {
        return view('demo');
    }



    /**
     * Handle file upload from the demo interface.
     *
     * @param Request $request The incoming request
     *
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:10240', // 10MB max
            'quality' => 'nullable|integer|min:1|max:100',
            'generate_webp' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $quality = $request->input('quality', 80);
        $generateWebp = $request->boolean('generate_webp', true);
        $originalName = $file->getClientOriginalName();
        $originalSize = $file->getSize();
        $extension = $file->getClientOriginalExtension();

        // Generate unique filename for storage
        $uniqueName = Str::uuid() . '.' . $extension;
        
        // Store the original file
        $originalPath = $file->storeAs('uploads/original', $uniqueName, 'public');

        // Create optimization task
        $task = OptimizationTask::create([
            'original_filename' => $originalName,
            'original_path' => $originalPath,
            'original_size' => $originalSize,
            'quality' => $quality,
            'generate_webp' => $generateWebp,
        ]);

        // Log task creation
        $this->logger->logTaskCreated($task);

        // Dispatch the optimization job
        OptimizeFileJob::dispatch($task);

        return response()->json([
            'success' => true,
            'task_id' => $task->task_id,
            'status' => 'pending',
            'original_file' => [
                'name' => $originalName,
                'size' => $originalSize,
                'size_formatted' => FormatHelper::bytes($originalSize),
            ],
        ]);
    }

    /**
     * Check the status of an optimization task for demo interface.
     *
     * @param string $taskId The task ID
     *
     * @return JsonResponse
     */
    public function status(string $taskId): JsonResponse
    {
        $task = OptimizationTask::where('task_id', $taskId)->first();

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
            ], 404);
        }

        if ($task->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Task has expired',
            ], 410);
        }

        $response = [
            'success' => true,
            'task_id' => $task->task_id,
            'status' => $task->status,
            'original_file' => [
                'name' => $task->original_filename,
                'size' => $task->original_size,
                'size_formatted' => FormatHelper::bytes($task->original_size),
            ],
        ];

        if ($task->status === 'completed') {
            $response['optimization'] = [
                'compression_ratio' => $task->compression_ratio,
                'size_reduction' => $task->size_reduction,
                'size_reduction_formatted' => FormatHelper::bytes($task->size_reduction),
                'processing_time' => $task->processing_time,
                'optimized_size' => $task->optimized_size,
                'optimized_size_formatted' => FormatHelper::bytes($task->optimized_size),
            ];

            // Add size increase warning if optimization didn't reduce size
            if ($task->size_reduction <= 0) {
                $response['optimization']['size_increase_prevented'] = true;
                $response['optimization']['note'] = 'Original file was already well-optimized';
            }
            
            // Include WebP information if generated
            if ($task->webp_generated) {
                $response['webp'] = [
                    'compression_ratio' => $task->webp_compression_ratio,
                    'size_reduction' => $task->webp_size_reduction,
                    'size_reduction_formatted' => FormatHelper::bytes($task->webp_size_reduction),
                    'webp_size' => $task->webp_size,
                    'webp_size_formatted' => FormatHelper::bytes($task->webp_size),
                ];

                if ($task->webp_size_reduction <= 0) {
                    $response['webp']['size_increase_warning'] = true;
                    $response['webp']['note'] = 'WebP conversion did not reduce file size for this image';
                }
            }
        }

        if ($task->status === 'failed') {
            $response['error'] = $task->error_message;
        }

        return response()->json($response);
    }

} 