<?php

use App\Models\OptimizationTask;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Database Integration', function () {
    describe('Migrations', function () {
        it('creates optimization_tasks table correctly', function () {
            expect(Schema::hasTable('optimization_tasks'))->toBe(true);
            
            expect(Schema::hasColumn('optimization_tasks', 'id'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'task_id'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'status'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'original_filename'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'original_path'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'optimized_path'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'webp_path'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'original_size'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'optimized_size'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'webp_size'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'quality'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'compression_ratio'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'webp_compression_ratio'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'size_reduction'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'webp_size_reduction'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'algorithm'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'processing_time'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'webp_processing_time'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'webp_generated'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'error_message'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'started_at'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'completed_at'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'expires_at'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'created_at'))->toBe(true);
            expect(Schema::hasColumn('optimization_tasks', 'updated_at'))->toBe(true);
        });

        it('creates users table correctly', function () {
            expect(Schema::hasTable('users'))->toBe(true);
            
            expect(Schema::hasColumn('users', 'id'))->toBe(true);
            expect(Schema::hasColumn('users', 'name'))->toBe(true);
            expect(Schema::hasColumn('users', 'email'))->toBe(true);
            expect(Schema::hasColumn('users', 'email_verified_at'))->toBe(true);
            expect(Schema::hasColumn('users', 'password'))->toBe(true);
            expect(Schema::hasColumn('users', 'remember_token'))->toBe(true);
            expect(Schema::hasColumn('users', 'created_at'))->toBe(true);
            expect(Schema::hasColumn('users', 'updated_at'))->toBe(true);
        });

        it('creates cache table correctly', function () {
            expect(Schema::hasTable('cache'))->toBe(true);
            
            expect(Schema::hasColumn('cache', 'key'))->toBe(true);
            expect(Schema::hasColumn('cache', 'value'))->toBe(true);
            expect(Schema::hasColumn('cache', 'expiration'))->toBe(true);
        });

        it('creates jobs table correctly', function () {
            expect(Schema::hasTable('jobs'))->toBe(true);
            
            expect(Schema::hasColumn('jobs', 'id'))->toBe(true);
            expect(Schema::hasColumn('jobs', 'queue'))->toBe(true);
            expect(Schema::hasColumn('jobs', 'payload'))->toBe(true);
            expect(Schema::hasColumn('jobs', 'attempts'))->toBe(true);
            expect(Schema::hasColumn('jobs', 'reserved_at'))->toBe(true);
            expect(Schema::hasColumn('jobs', 'available_at'))->toBe(true);
            expect(Schema::hasColumn('jobs', 'created_at'))->toBe(true);
        });
    });

    describe('Model Relationships and Constraints', function () {
        it('enforces unique task_id constraint', function () {
            $taskId = 'unique-task-id';
            
            OptimizationTask::factory()->create(['task_id' => $taskId]);
            
            expect(fn() => OptimizationTask::factory()->create(['task_id' => $taskId]))
                ->toThrow(\Illuminate\Database\QueryException::class);
        });

        it('allows null values where appropriate', function () {
            $task = OptimizationTask::create([
                'original_filename' => 'test.jpg',
                'original_path' => 'uploads/test.jpg',
                'original_size' => 1000,
                'quality' => 80,
                'optimized_path' => null,
                'webp_path' => null,
                'optimized_size' => null,
                'webp_size' => null,
                'compression_ratio' => null,
                'webp_compression_ratio' => null,
                'size_reduction' => null,
                'webp_size_reduction' => null,
                'algorithm' => null,
                'processing_time' => null,
                'webp_processing_time' => null,
                'error_message' => null,
            ]);

            expect($task)->toBeInstanceOf(OptimizationTask::class);
            expect($task->optimized_path)->toBeNull();
            expect($task->webp_path)->toBeNull();
        });
    });

    describe('Database Queries and Scopes', function () {
        it('can find tasks by task_id', function () {
            $task = OptimizationTask::factory()->create();
            
            $foundTask = OptimizationTask::where('task_id', $task->task_id)->first();
            
            expect($foundTask)->not->toBeNull();
            expect($foundTask->id)->toBe($task->id);
        });

        it('can query expired tasks using scope', function () {
            $expiredTask = OptimizationTask::factory()->expired()->create();
            $activeTask = OptimizationTask::factory()->create();
            
            $expiredTasks = OptimizationTask::expired()->get();
            
            expect($expiredTasks)->toHaveCount(1);
            expect($expiredTasks->first()->id)->toBe($expiredTask->id);
        });

        it('can filter by status', function () {
            OptimizationTask::factory()->create(['status' => 'pending']);
            OptimizationTask::factory()->create(['status' => 'completed']);
            OptimizationTask::factory()->create(['status' => 'failed']);
            
            $pendingTasks = OptimizationTask::where('status', 'pending')->get();
            $completedTasks = OptimizationTask::where('status', 'completed')->get();
            
            expect($pendingTasks)->toHaveCount(1);
            expect($completedTasks)->toHaveCount(1);
        });
    });

    describe('Data Integrity', function () {
        it('maintains data consistency during updates', function () {
            $task = OptimizationTask::factory()->create(['status' => 'pending']);
            
            $originalCreatedAt = $task->created_at;
            
            $task->update(['status' => 'processing']);
            
            expect($task->status)->toBe('processing');
            expect($task->created_at->equalTo($originalCreatedAt))->toBe(true);
            expect($task->updated_at->gte($originalCreatedAt))->toBe(true);
        });

        it('handles concurrent access safely', function () {
            $task = OptimizationTask::factory()->create();
            
            // Simulate concurrent access
            $task1 = OptimizationTask::find($task->id);
            $task2 = OptimizationTask::find($task->id);
            
            $task1->update(['status' => 'processing']);
            $task2->update(['status' => 'completed']);
            
            $finalTask = OptimizationTask::find($task->id);
            expect($finalTask->status)->toBe('completed'); // Last update wins
        });
    });
}); 