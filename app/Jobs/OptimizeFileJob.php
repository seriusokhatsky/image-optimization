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
            $logger->logTaskProcessingStarted($this->task);
            
            // Mark task as processing
            $this->task->markAsProcessing();

            // Check if original file exists
            if (!Storage::disk('public')->exists($this->task->original_path)) {
                throw new \Exception('Original file not found');
            }

            // Get the file path for processing
            $fullPath = Storage::disk('public')->path($this->task->original_path);

            // Create a temporary UploadedFile object for the service
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
                $this->task->quality
            );

            if ($optimizationResult['optimized']) {
                // The actual optimized file path where FileOptimizationService stored the file
                $actualOptimizedPath = 'uploads/optimized/' . $this->task->original_filename;
                
                // Mark as completed with optimization data
                $this->task->markAsCompleted([
                    'optimized_path' => $actualOptimizedPath,
                    'optimized_size' => $optimizationResult['optimized_size'],
                    'compression_ratio' => $optimizationResult['compression_ratio'],
                    'size_reduction' => $optimizationResult['size_reduction'],
                    'processing_time' => $optimizationResult['processing_time'],
                ]);

                // Try to generate WebP copy if enabled and supported
                if ($this->task->generate_webp) {
                    $mimeType = $uploadedFile->getMimeType();
                    if ($optimizationService->supportsWebpConversion($mimeType)) {
                        $webpResult = $optimizationService->generateWebpCopy(
                            $actualOptimizedPath,
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
                        }
                    }
                }

                $logger->logTaskCompleted($this->task, $optimizationResult);
            } else {
                $errorMessage = $optimizationResult['reason'] ?? 'Optimization failed';
                $logger->logTaskFailed($this->task, $errorMessage);
                throw new \Exception($errorMessage);
            }

        } catch (\Exception $e) {
            $logger->logTaskFailed($this->task, $e->getMessage(), $e);
            $this->task->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $logger = app(OptimizationLogger::class);
        $logger->logTaskFailed($this->task, $exception->getMessage(), $exception);
        $this->task->markAsFailed($exception->getMessage());
    }
} 