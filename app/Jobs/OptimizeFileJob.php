<?php

namespace App\Jobs;

use App\Models\OptimizationTask;
use App\Services\FileOptimizationService;
use App\Services\OptimizationLogger;
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

    public function handle(FileOptimizationService $optimizationService, OptimizationLogger $logger): void
    {
        try {
            Log::info("Starting optimization for task {$this->task->task_id}");
            $logger->logTaskProcessingStarted($this->task);
            
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
                    'optimized_path' => $this->task->generateOptimizedPath(),
                    'optimized_size' => $optimizationResult['optimized_size'],
                    'compression_ratio' => $optimizationResult['compression_ratio'],
                    'size_reduction' => $optimizationResult['size_reduction'],
                    'algorithm' => $optimizationResult['algorithm'],
                    'processing_time' => $optimizationResult['processing_time'],
                ]);

                // Try to generate WebP copy if supported
                $mimeType = $uploadedFile->getMimeType();
                if ($optimizationService->supportsWebpConversion($mimeType)) {
                    Log::info("Generating WebP copy for task {$this->task->task_id}");
                    
                    $webpResult = $optimizationService->generateWebpCopy(
                        $this->task->optimized_path,
                        $mimeType,
                        $this->task->quality
                    );

                    if ($webpResult['success']) {
                        $this->task->updateWebpData([
                            'webp_path' => $webpResult['webp_path'],
                            'webp_size' => $webpResult['webp_size'],
                            'webp_compression_ratio' => $webpResult['webp_compression_ratio'],
                            'webp_size_reduction' => $webpResult['webp_size_reduction'],
                            'webp_processing_time' => $webpResult['webp_processing_time'],
                            'webp_generated' => true,
                        ]);
                        
                        Log::info("WebP copy generated successfully for task {$this->task->task_id}");
                    } else {
                        Log::warning("WebP generation failed for task {$this->task->task_id}: " . ($webpResult['reason'] ?? 'Unknown error'));
                    }
                } else {
                    Log::info("WebP conversion not supported for MIME type {$mimeType} in task {$this->task->task_id}");
                }

                Log::info("Optimization completed for task {$this->task->task_id}");
                $logger->logTaskCompleted($this->task, $optimizationResult);
            } else {
                $errorMessage = $optimizationResult['reason'] ?? 'Optimization failed';
                $logger->logTaskFailed($this->task, $errorMessage);
                throw new \Exception($errorMessage);
            }

        } catch (\Exception $e) {
            Log::error("Optimization failed for task {$this->task->task_id}: {$e->getMessage()}");
            $logger->logTaskFailed($this->task, $e->getMessage(), $e);
            $this->task->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job failed for task {$this->task->task_id}: {$exception->getMessage()}");
        
        // Log the final failure
        $logger = app(OptimizationLogger::class);
        $logger->logTaskFailed($this->task, $exception->getMessage(), $exception);
        
        $this->task->markAsFailed($exception->getMessage());
    }
} 