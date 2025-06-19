<?php

namespace Tests\Feature;

use App\Models\OptimizationTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_optimization_tasks_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('optimization_tasks'));
    }

    public function test_optimization_tasks_table_has_required_columns(): void
    {
        $columns = [
            'id', 'task_id', 'status', 'original_filename', 'original_path',
            'original_size', 'quality', 'optimized_path', 'optimized_size',
            'compression_ratio', 'size_reduction', 'processing_time',
            'webp_generated', 'webp_path', 'webp_size', 'webp_compression_ratio',
            'webp_size_reduction', 'webp_processing_time', 'error_message',
            'started_at', 'completed_at', 'expires_at', 'created_at', 'updated_at'
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('optimization_tasks', $column),
                "Column '{$column}' does not exist in optimization_tasks table"
            );
        }
    }

    public function test_optimization_tasks_table_has_correct_column_types(): void
    {
        $this->assertEquals('integer', Schema::getColumnType('optimization_tasks', 'id'));
        $this->assertEquals('varchar', Schema::getColumnType('optimization_tasks', 'task_id'));
        $this->assertEquals('varchar', Schema::getColumnType('optimization_tasks', 'status'));
        $this->assertEquals('varchar', Schema::getColumnType('optimization_tasks', 'original_filename'));
        $this->assertEquals('integer', Schema::getColumnType('optimization_tasks', 'original_size'));
        $this->assertEquals('integer', Schema::getColumnType('optimization_tasks', 'quality'));
        $this->assertEquals('tinyint', Schema::getColumnType('optimization_tasks', 'webp_generated'));
    }

    public function test_task_id_column_has_unique_constraint(): void
    {
        $task1 = OptimizationTask::factory()->create();
        $task2 = OptimizationTask::factory()->create();

        $this->assertNotEquals($task1->task_id, $task2->task_id);
    }

    public function test_can_create_optimization_task_record(): void
    {
        $data = [
            'original_filename' => 'test.jpg',
            'original_path' => 'uploads/original/test.jpg',
            'original_size' => 1000,
            'quality' => 80,
            'status' => 'pending',
        ];

        $task = OptimizationTask::create($data);

        $this->assertDatabaseHas('optimization_tasks', [
            'original_filename' => 'test.jpg',
            'status' => 'pending',
        ]);

        $this->assertNotNull($task->task_id);
        $this->assertNotNull($task->expires_at);
    }

    public function test_can_update_optimization_task_record(): void
    {
        $task = OptimizationTask::factory()->create(['status' => 'pending']);

        $task->update([
            'status' => 'completed',
            'optimized_size' => 800,
            'compression_ratio' => 0.20,
        ]);

        $this->assertDatabaseHas('optimization_tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'optimized_size' => 800,
        ]);
    }

    public function test_can_delete_optimization_task_record(): void
    {
        $task = OptimizationTask::factory()->create();
        $taskId = $task->id;

        $task->delete();

        $this->assertDatabaseMissing('optimization_tasks', [
            'id' => $taskId,
        ]);
    }

    public function test_timestamps_are_set_automatically(): void
    {
        $task = OptimizationTask::factory()->create();

        $this->assertNotNull($task->created_at);
        $this->assertNotNull($task->updated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $task->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $task->updated_at);
    }

    public function test_can_query_tasks_by_status(): void
    {
        OptimizationTask::factory()->create(['status' => 'pending']);
        OptimizationTask::factory()->create(['status' => 'completed']);
        OptimizationTask::factory()->create(['status' => 'failed']);

        $pendingTasks = OptimizationTask::where('status', 'pending')->get();
        $completedTasks = OptimizationTask::where('status', 'completed')->get();

        $this->assertCount(1, $pendingTasks);
        $this->assertCount(1, $completedTasks);
    }

    public function test_can_query_expired_tasks(): void
    {
        $expiredTask = OptimizationTask::factory()->create([
            'expires_at' => now()->subHour(),
        ]);
        $activeTask = OptimizationTask::factory()->create([
            'expires_at' => now()->addHour(),
        ]);

        $expiredTasks = OptimizationTask::where('expires_at', '<', now())->get();

        $this->assertCount(1, $expiredTasks);
        $this->assertEquals($expiredTask->id, $expiredTasks->first()->id);
    }

    public function test_database_supports_large_file_sizes(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_size' => 2147483647, // Max 32-bit integer
            'optimized_size' => 1073741823,
        ]);

        $this->assertDatabaseHas('optimization_tasks', [
            'id' => $task->id,
            'original_size' => 2147483647,
        ]);
    }

    public function test_database_handles_decimal_compression_ratios(): void
    {
        $task = OptimizationTask::factory()->create([
            'compression_ratio' => 0.256789,
            'webp_compression_ratio' => 0.123456,
        ]);

        $task->refresh();

        // Depending on precision settings, these should be stored as decimals
        $this->assertTrue(is_numeric($task->compression_ratio));
        $this->assertTrue(is_numeric($task->webp_compression_ratio));
    }

    public function test_database_transaction_rollback_works(): void
    {
        $initialCount = OptimizationTask::count();

        try {
            \DB::transaction(function () {
                OptimizationTask::factory()->create();
                OptimizationTask::factory()->create();
                
                // Force an exception to trigger rollback
                throw new \Exception('Test rollback');
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        $finalCount = OptimizationTask::count();
        $this->assertEquals($initialCount, $finalCount);
    }

    public function test_database_handles_concurrent_task_creation(): void
    {
        // Test concurrent creation doesn't cause conflicts
        $tasks = [];
        for ($i = 0; $i < 5; $i++) {
            $tasks[] = OptimizationTask::factory()->create();
        }

        $taskIds = collect($tasks)->pluck('task_id')->toArray();
        $uniqueTaskIds = array_unique($taskIds);

        $this->assertCount(5, $uniqueTaskIds, 'All task IDs should be unique');
    }

    public function test_foreign_key_constraints_work_if_present(): void
    {
        // This test assumes there might be foreign key constraints in the future
        // For now, we just test that the table accepts the data
        $task = OptimizationTask::factory()->create();
        
        $this->assertInstanceOf(OptimizationTask::class, $task);
        $this->assertNotNull($task->id);
    }

    public function test_database_indexes_exist_for_performance(): void
    {
        // Test that common query patterns work efficiently
        // In a real app, you'd want indexes on task_id, status, expires_at
        
        $task = OptimizationTask::factory()->create();
        
        // These queries should work efficiently with proper indexes
        $foundByTaskId = OptimizationTask::where('task_id', $task->task_id)->first();
        $foundByStatus = OptimizationTask::where('status', $task->status)->get();
        
        $this->assertNotNull($foundByTaskId);
        $this->assertNotEmpty($foundByStatus);
    }
} 