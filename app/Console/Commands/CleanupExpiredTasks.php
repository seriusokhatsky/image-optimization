<?php

namespace App\Console\Commands;

use App\Models\OptimizationTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredTasks extends Command
{
    protected $signature = 'optimize:cleanup';
    protected $description = 'Clean up expired optimization tasks and their files';

    public function handle()
    {
        $expiredTasks = OptimizationTask::expired()->get();
        
        $count = 0;
        foreach ($expiredTasks as $task) {
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
    }
} 