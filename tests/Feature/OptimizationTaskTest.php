<?php

namespace Tests\Feature;

use App\Models\OptimizationTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptimizationTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_optimization_task(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_filename' => 'test.jpg',
            'original_size' => 1000,
            'quality' => 80,
        ]);

        $this->assertInstanceOf(OptimizationTask::class, $task);
        $this->assertEquals('test.jpg', $task->original_filename);
        $this->assertEquals(1000, $task->original_size);
        $this->assertEquals(80, $task->quality);
        $this->assertEquals('pending', $task->status);
    }

    public function test_automatically_generates_unique_task_id(): void
    {
        $task1 = OptimizationTask::factory()->create();
        $task2 = OptimizationTask::factory()->create();

        $this->assertNotNull($task1->task_id);
        $this->assertNotNull($task2->task_id);
        $this->assertNotEquals($task1->task_id, $task2->task_id);
    }

    public function test_can_mark_task_as_processing(): void
    {
        $task = OptimizationTask::factory()->create(['status' => 'pending']);

        $task->markAsProcessing();

        $this->assertEquals('processing', $task->status);
        $this->assertNotNull($task->started_at);
    }

    public function test_can_mark_task_as_completed(): void
    {
        $task = OptimizationTask::factory()->create(['status' => 'processing']);

        $optimizationData = [
            'optimized_path' => 'uploads/optimized/test.jpg',
            'optimized_size' => 800,
            'compression_ratio' => 0.20,
            'size_reduction' => 200,
            'processing_time' => '150.5 ms',
        ];

        $task->markAsCompleted($optimizationData);

        $this->assertEquals('completed', $task->status);
        $this->assertEquals('uploads/optimized/test.jpg', $task->optimized_path);
        $this->assertEquals(800, $task->optimized_size);
        $this->assertEquals(0.20, $task->compression_ratio);
        $this->assertNotNull($task->completed_at);
    }

    public function test_can_mark_task_as_failed(): void
    {
        $task = OptimizationTask::factory()->create(['status' => 'processing']);

        $task->markAsFailed('Optimization failed');

        $this->assertEquals('failed', $task->status);
        $this->assertEquals('Optimization failed', $task->error_message);
        $this->assertNotNull($task->completed_at);
    }

    public function test_can_update_webp_data(): void
    {
        $task = OptimizationTask::factory()->create();

        $webpData = [
            'webp_path' => 'uploads/webp/test.webp',
            'webp_size' => 600,
            'webp_compression_ratio' => 0.40,
            'webp_size_reduction' => 400,
            'webp_processing_time' => '75.5 ms',
            'webp_generated' => true,
        ];

        $task->updateWebpData($webpData);

        $this->assertEquals('uploads/webp/test.webp', $task->webp_path);
        $this->assertEquals(600, $task->webp_size);
        $this->assertEquals(0.40, $task->webp_compression_ratio);
        $this->assertTrue($task->webp_generated);
    }

    public function test_can_check_if_task_is_expired(): void
    {
        $activeTask = OptimizationTask::factory()->create([
            'expires_at' => now()->addHours(12),
        ]);

        $expiredTask = OptimizationTask::factory()->create([
            'expires_at' => now()->subHours(1),
        ]);

        $this->assertFalse($activeTask->isExpired());
        $this->assertTrue($expiredTask->isExpired());
    }

    public function test_can_scope_expired_tasks(): void
    {
        OptimizationTask::factory()->create(['expires_at' => now()->addHours(12)]);
        OptimizationTask::factory()->create(['expires_at' => now()->subHours(1)]);
        OptimizationTask::factory()->create(['expires_at' => now()->subHours(2)]);

        $expiredTasks = OptimizationTask::expired()->get();

        $this->assertCount(2, $expiredTasks);
    }

    public function test_generates_optimized_filename_correctly(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_filename' => 'my_photo.jpeg',
        ]);

        $optimizedFilename = $task->getOptimizedFilename();

        $this->assertEquals('my_photo-optimized.jpeg', $optimizedFilename);
    }

    public function test_generates_optimized_path_correctly(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_path' => 'uploads/original/uuid-file.jpg',
        ]);

        $optimizedPath = $task->generateOptimizedPath();

        $this->assertEquals('uploads/optimized/uuid-file.jpg', $optimizedPath);
    }

    public function test_generates_webp_filename_correctly(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_filename' => 'my_photo.jpeg',
        ]);

        $webpFilename = $task->getWebpFilename();

        $this->assertEquals('my_photo.jpeg.webp', $webpFilename);
    }

    public function test_generates_webp_path_correctly(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_path' => 'uploads/original/uuid-file.jpg',
        ]);

        $webpPath = $task->generateWebpPath();

        $this->assertEquals('uploads/webp/uuid-file.jpg.webp', $webpPath);
    }

    public function test_expires_at_is_set_automatically(): void
    {
        $task = OptimizationTask::factory()->create();

        $this->assertNotNull($task->expires_at);
        $this->assertTrue($task->expires_at->greaterThan(now()));
    }

    public function test_task_id_is_not_overwritten_if_provided(): void
    {
        $customTaskId = 'custom-task-id-123';
        
        $task = OptimizationTask::factory()->create([
            'task_id' => $customTaskId,
        ]);

        $this->assertEquals($customTaskId, $task->task_id);
    }

    public function test_casts_are_applied_correctly(): void
    {
        $task = OptimizationTask::factory()->create([
            'compression_ratio' => 0.25,
            'webp_compression_ratio' => 0.35,
            'webp_generated' => true,
            'started_at' => '2023-01-01 12:00:00',
        ]);

        $this->assertEquals(0.25, $task->compression_ratio);
        $this->assertEquals(0.35, $task->webp_compression_ratio);
        $this->assertIsBool($task->webp_generated);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $task->started_at);
    }
} 