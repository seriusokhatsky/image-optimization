<?php

namespace App\Services;

use App\Models\OptimizationTask;
use Illuminate\Support\Facades\Log;

class OptimizationLogger
{
    protected $logger;

    public function __construct()
    {
        $this->logger = Log::channel('optimization');
    }

    /**
     * Log task creation
     */
    public function logTaskCreated(OptimizationTask $task): void
    {
        $this->logger->info('TASK_CREATED', [
            'task_id' => $task->task_id,
            'original_filename' => $task->original_filename,
            'original_size' => $task->original_size,
            'quality' => $task->quality,
            'created_at' => $task->created_at->toISOString(),
            'expires_at' => $task->expires_at->toISOString(),
        ]);
    }

    /**
     * Log task processing start
     */
    public function logTaskProcessingStarted(OptimizationTask $task): void
    {
        $this->logger->info('TASK_PROCESSING_STARTED', [
            'task_id' => $task->task_id,
            'original_filename' => $task->original_filename,
            'original_path' => $task->original_path,
            'started_at' => now()->toISOString(),
        ]);
    }

    /**
     * Log successful optimization
     */
    public function logTaskCompleted(OptimizationTask $task, array $optimizationResult): void
    {
        $this->logger->info('TASK_COMPLETED_SUCCESS', [
            'task_id' => $task->task_id,
            'original_filename' => $task->original_filename,
            'original_size' => $task->original_size,
            'optimized_size' => $optimizationResult['optimized_size'],
            'compression_ratio' => $optimizationResult['compression_ratio'],
            'size_reduction' => $optimizationResult['size_reduction'],
            'algorithm' => $optimizationResult['algorithm'],
            'processing_time' => $optimizationResult['processing_time'],
            'optimized_path' => $task->optimized_path,
            'completed_at' => now()->toISOString(),
            'result' => 'SUCCESS',
        ]);
    }

    /**
     * Log optimization failure
     */
    public function logTaskFailed(OptimizationTask $task, string $errorMessage, ?\Throwable $exception = null): void
    {
        $logData = [
            'task_id' => $task->task_id,
            'original_filename' => $task->original_filename,
            'original_size' => $task->original_size,
            'error_message' => $errorMessage,
            'failed_at' => now()->toISOString(),
            'result' => 'FAILED',
        ];

        if ($exception) {
            $logData['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $this->logger->error('TASK_COMPLETED_FAILED', $logData);
    }

    /**
     * Log task download
     */
    public function logTaskDownloaded(OptimizationTask $task): void
    {
        $this->logger->info('TASK_DOWNLOADED', [
            'task_id' => $task->task_id,
            'original_filename' => $task->original_filename,
            'optimized_filename' => $task->getOptimizedFilename(),
            'downloaded_at' => now()->toISOString(),
            'task_cleanup' => 'SCHEDULED',
        ]);
    }

    /**
     * Log task expiration cleanup
     */
    public function logTaskExpired(OptimizationTask $task): void
    {
        $this->logger->info('TASK_EXPIRED_CLEANUP', [
            'task_id' => $task->task_id,
            'original_filename' => $task->original_filename,
            'status' => $task->status,
            'expired_at' => $task->expires_at->toISOString(),
            'cleaned_up_at' => now()->toISOString(),
        ]);
    }

    /**
     * Log general optimization statistics
     */
    public function logDailyStats(array $stats): void
    {
        $this->logger->info('DAILY_OPTIMIZATION_STATS', [
            'date' => now()->toDateString(),
            'total_tasks' => $stats['total_tasks'],
            'successful_tasks' => $stats['successful_tasks'],
            'failed_tasks' => $stats['failed_tasks'],
            'total_original_size' => $stats['total_original_size'],
            'total_optimized_size' => $stats['total_optimized_size'],
            'total_bytes_saved' => $stats['total_bytes_saved'],
            'average_compression_ratio' => $stats['average_compression_ratio'],
        ]);
    }
} 