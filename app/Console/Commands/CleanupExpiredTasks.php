<?php

namespace App\Console\Commands;

use App\Models\OptimizationTask;
use App\Services\OptimizationLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredTasks extends Command
{
    protected $signature = 'optimize:cleanup';
    protected $description = 'Clean up expired optimization tasks and their files';

    public function handle(OptimizationLogger $logger)
    {
        $expiredTasks = OptimizationTask::expired()->get();
        
        $count = 0;
        foreach ($expiredTasks as $task) {
            // Log the expiration
            $logger->logTaskExpired($task);
            
            // Delete files
            Storage::disk('public')->delete($task->original_path);
            if ($task->optimized_path) {
                Storage::disk('public')->delete($task->optimized_path);
            }
            
            // Delete task
            $task->delete();
            $count++;
        }

        $this->info("Cleaned up {$count} expired tasks");
        
        // Log summary if any tasks were cleaned
        if ($count > 0) {
            $logger->logDailyStats([
                'expired_tasks_cleaned' => $count,
                'cleanup_date' => now()->toDateString(),
            ]);
        }
    }
} 