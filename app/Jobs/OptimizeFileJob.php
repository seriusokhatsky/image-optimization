<?php

namespace App\Jobs;

use App\Models\OptimizationTask;
use App\Services\FileOptimizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class OptimizeFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3;

    public function __construct(
        public OptimizationTask $task
    ) {}

    public function handle(FileOptimizationService $optimizationService): void
    {
        try {
            Log::info("Starting optimization for task {$this->task->task_id}");
            
            // Mark task as processing
            $this->task->markAsProcessing();

            // Check if original file exists
            if (!Storage::disk('public')->exists($this->task->original_path)) {
                throw new \Exception('Original file not found');
            }

            // Get the file path for processing
            $fullPath = Storage::disk('public')->path($this->task->original_path);
            $extension = pathinfo($this->task->original_filename, PATHINFO_EXTENSION);

            // Create a temporary UploadedFile object for the service
            $tempFile = new \Illuminate\Http\File($fullPath);
            $uploadedFile = new UploadedFile(
                $fullPath,
                $this->task->original_filename,
                mime_content_type($fullPath),
                null,
                true
            );

            // Perform optimization
            $optimizationResult = $optimizationService->optimize(
                $uploadedFile,
                $extension,
                $this->task->original_path,
                $this->task->quality
            );

            if ($optimizationResult['optimized']) {
                // Mark as completed with optimization data
                $this->task->markAsCompleted([
                    'optimized_path' => 'uploads/optimized/' . basename($this->task->original_path),
                    'optimized_size' => $optimizationResult['optimized_size'],
                    'compression_ratio' => $optimizationResult['compression_ratio'],
                    'size_reduction' => $optimizationResult['size_reduction'],
                    'algorithm' => $optimizationResult['algorithm'],
                    'processing_time' => $optimizationResult['processing_time'],
                ]);

                Log::info("Optimization completed for task {$this->task->task_id}");
            } else {
                throw new \Exception($optimizationResult['reason'] ?? 'Optimization failed');
            }

        } catch (\Exception $e) {
            Log::error("Optimization failed for task {$this->task->task_id}: {$e->getMessage()}");
            $this->task->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job failed for task {$this->task->task_id}: {$exception->getMessage()}");
        $this->task->markAsFailed($exception->getMessage());
    }
} 