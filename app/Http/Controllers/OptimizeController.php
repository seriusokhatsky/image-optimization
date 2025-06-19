<?php

namespace App\Http\Controllers;

use App\Jobs\OptimizeFileJob;
use App\Models\OptimizationTask;
use App\Services\OptimizationLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OptimizeController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private OptimizationLogger $logger
    ) {}

    /**
     * Submit a file for optimization (async)
     */
    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'quality' => 'nullable|integer|min:1|max:100',
        ]);

        $file = $request->file('file');
        $quality = $request->input('quality', 80);
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
        ]);

        // Log task creation
        $this->logger->logTaskCreated($task);

        // Dispatch the optimization job
        OptimizeFileJob::dispatch($task);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully. Optimization in progress.',
            'data' => [
                'task_id' => $task->task_id,
                'status' => 'pending',
                'original_file' => [
                    'name' => $originalName,
                    'size' => $originalSize,
                ],
                'estimated_completion' => now()->addMinutes(2)->toISOString(),
            ],
        ], 202); // 202 Accepted
    }

    /**
     * Check the status of an optimization task
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
            ], 410); // 410 Gone
        }

        $response = [
            'success' => true,
            'data' => [
                'task_id' => $task->task_id,
                'status' => $task->status,
                'original_file' => [
                    'name' => $task->original_filename,
                    'size' => $task->original_size,
                ],
                'created_at' => $task->created_at->toISOString(),
            ],
        ];

        if ($task->status === 'processing') {
            $response['data']['started_at'] = $task->started_at?->toISOString();
        }

        if ($task->status === 'completed') {
            $response['data']['optimization'] = [
                'compression_ratio' => $task->compression_ratio,
                'size_reduction' => $task->size_reduction,
                'algorithm' => $task->algorithm,
                'processing_time' => $task->processing_time . ' ms',
                'optimized_size' => $task->optimized_size,
            ];

            // Add size increase warning if optimization didn't reduce size
            if ($task->size_reduction <= 0) {
                $response['data']['optimization']['size_increase_prevented'] = true;
                $response['data']['optimization']['note'] = 'Original file was already well-optimized';
            }
            
            // Include WebP information if generated
            if ($task->webp_generated) {
                $response['data']['webp'] = [
                    'compression_ratio' => $task->webp_compression_ratio,
                    'size_reduction' => $task->webp_size_reduction,
                    'processing_time' => $task->webp_processing_time,
                    'webp_size' => $task->webp_size,
                ];

                // Add warning for WebP if it didn't provide benefits
                if ($task->webp_size_reduction <= 0) {
                    $response['data']['webp']['size_increase_warning'] = true;
                    $response['data']['webp']['note'] = 'WebP conversion did not reduce file size for this image';
                }

                $response['data']['webp_download_url'] = route('optimize.download.webp', $task->task_id);
            }
            
            $response['data']['completed_at'] = $task->completed_at->toISOString();
            $response['data']['download_url'] = route('optimize.download', $task->task_id);
        }

        if ($task->status === 'failed') {
            $response['data']['error'] = $task->error_message;
            $response['data']['completed_at'] = $task->completed_at->toISOString();
        }

        return response()->json($response);
    }

    /**
     * Download the optimized file
     */
    public function download(string $taskId)
    {
        $task = OptimizationTask::where('task_id', $taskId)
            ->where('status', 'completed')
            ->first();

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found or not completed',
            ], 404);
        }

        if ($task->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Task has expired',
            ], 410);
        }

        if (!Storage::disk('public')->exists($task->optimized_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Optimized file not found',
            ], 404);
        }

        // Get file path and name for download
        $filePath = Storage::disk('public')->path($task->optimized_path);
        $optimizedFileName = $task->getOptimizedFilename();

        // Log the download
        $this->logger->logTaskDownloaded($task);

        // Clean up the original file and task record immediately
        // (optimized file will be deleted by Laravel after download)
        Storage::disk('public')->delete($task->original_path);
        if ($task->webp_path) {
            Storage::disk('public')->delete($task->webp_path);
        }
        $task->delete();

        // Download the file and auto-delete it after sending
        return response()->download($filePath, $optimizedFileName)->deleteFileAfterSend();
    }

    /**
     * Download the WebP version of the optimized file
     */
    public function downloadWebp(string $taskId)
    {
        $task = OptimizationTask::where('task_id', $taskId)
            ->where('status', 'completed')
            ->where('webp_generated', true)
            ->first();

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found, not completed, or WebP not generated',
            ], 404);
        }

        if ($task->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Task has expired',
            ], 410);
        }

        if (!Storage::disk('public')->exists($task->webp_path)) {
            return response()->json([
                'success' => false,
                'message' => 'WebP file not found',
            ], 404);
        }

        // Get file path and name for download
        $filePath = Storage::disk('public')->path($task->webp_path);
        $webpFileName = $task->getWebpFilename();

        // Log the download
        $this->logger->logTaskDownloaded($task, 'webp');

        // Download the WebP file and auto-delete it after sending
        return response()->download($filePath, $webpFileName)->deleteFileAfterSend();
    }

    /**
     * Clean up task and associated files (used for expired tasks)
     */
    private function scheduleTaskCleanup(OptimizationTask $task): void
    {
        // Delete files
        Storage::disk('public')->delete($task->original_path);
        if ($task->optimized_path) {
            Storage::disk('public')->delete($task->optimized_path);
        }
        if ($task->webp_path) {
            Storage::disk('public')->delete($task->webp_path);
        }

        // Delete task record
        $task->delete();
    }
} 