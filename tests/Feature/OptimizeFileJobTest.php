<?php

namespace Tests\Feature;

use App\Jobs\OptimizeFileJob;
use App\Models\OptimizationTask;
use App\Services\FileOptimizationService;
use App\Services\OptimizationLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class OptimizeFileJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
    }

    public function test_can_instantiate_job(): void
    {
        $task = OptimizationTask::factory()->create();
        $job = new OptimizeFileJob($task);

        $this->assertInstanceOf(OptimizeFileJob::class, $job);
    }

    public function test_job_has_correct_queue_configuration(): void
    {
        $task = OptimizationTask::factory()->create();
        $job = new OptimizeFileJob($task);

        $this->assertEquals(300, $job->timeout);
        $this->assertEquals(3, $job->tries);
    }

    public function test_processes_task_successfully(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_path' => 'uploads/original/test.jpg',
            'original_size' => 1000000,
            'quality' => 80,
        ]);

        Storage::disk('public')->put($task->original_path, 'fake image content');

        $optimizationService = Mockery::mock(FileOptimizationService::class);
        $logger = Mockery::mock(OptimizationLogger::class);

        $optimizationService->shouldReceive('optimize')
            ->once()
            ->andReturn([
                'optimized' => true,
                'optimized_size' => 800000,
                'compression_ratio' => 0.20,
                'size_reduction' => 200000,
                'algorithm' => 'JPEG optimization with MozJPEG',
                'processing_time' => '150.50',
            ]);

        $optimizationService->shouldReceive('supportsWebpConversion')
            ->once()
            ->andReturn(true);

        $optimizationService->shouldReceive('generateWebpCopy')
            ->once()
            ->andReturn([
                'success' => true,
                'webp_path' => 'uploads/webp/test.jpg.webp',
                'webp_size' => 700000,
                'webp_compression_ratio' => 0.30,
                'webp_size_reduction' => 300000,
                'webp_processing_time' => '75.25',
            ]);

        $logger->shouldReceive('logTaskProcessingStarted')->once();
        $logger->shouldReceive('logTaskCompleted')->once();

        $job = new OptimizeFileJob($task);
        $job->handle($optimizationService, $logger);

        $task->refresh();
        $this->assertEquals('completed', $task->status);
        $this->assertEquals(800000, $task->optimized_size);
        $this->assertTrue($task->webp_generated);
    }

    public function test_handles_missing_original_file(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_path' => 'uploads/original/missing.jpg',
        ]);

        $optimizationService = Mockery::mock(FileOptimizationService::class);
        $logger = Mockery::mock(OptimizationLogger::class);

        $logger->shouldReceive('logTaskProcessingStarted')->once();
        $logger->shouldReceive('logTaskFailed')->once();

        $job = new OptimizeFileJob($task);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Original file not found');

        $job->handle($optimizationService, $logger);
    }

    public function test_generates_webp_for_supported_formats(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_path' => 'uploads/original/test.jpg',
            'original_filename' => 'test.jpg',
        ]);

        Storage::disk('public')->put($task->original_path, 'fake image content');

        $optimizationService = Mockery::mock(FileOptimizationService::class);
        $logger = Mockery::mock(OptimizationLogger::class);

        $optimizationService->shouldReceive('optimize')->once()->andReturn(['optimized' => true, 'optimized_size' => 800000, 'compression_ratio' => 0.20, 'size_reduction' => 200000, 'algorithm' => 'JPEG', 'processing_time' => '150']);
        $optimizationService->shouldReceive('supportsWebpConversion')->once()->andReturn(true);
        $optimizationService->shouldReceive('generateWebpCopy')->once()->andReturn(['success' => true, 'webp_path' => 'test.webp', 'webp_size' => 700000, 'webp_compression_ratio' => 0.30, 'webp_size_reduction' => 300000, 'webp_processing_time' => '75']);

        $logger->shouldReceive('logTaskProcessingStarted')->once();
        $logger->shouldReceive('logTaskCompleted')->once();

        $job = new OptimizeFileJob($task);
        $job->handle($optimizationService, $logger);

        $task->refresh();
        $this->assertTrue($task->webp_generated);
    }

    public function test_logs_optimization_start(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_path' => 'uploads/original/test.jpg',
        ]);

        Storage::disk('public')->put($task->original_path, 'fake content');

        $optimizationService = Mockery::mock(FileOptimizationService::class);
        $logger = Mockery::mock(OptimizationLogger::class);

        $optimizationService->shouldReceive('optimize')->andReturn(['optimized' => true, 'optimized_size' => 800000, 'compression_ratio' => 0.20, 'size_reduction' => 200000, 'algorithm' => 'JPEG', 'processing_time' => '150']);
        $optimizationService->shouldReceive('supportsWebpConversion')->andReturn(false);

        $logger->shouldReceive('logTaskProcessingStarted')->once();
        $logger->shouldReceive('logTaskCompleted')->once();

        $job = new OptimizeFileJob($task);
        $job->handle($optimizationService, $logger);

        // Just verify the job completed without throwing
        $this->assertTrue(true);
    }

    public function test_logs_optimization_completion(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_path' => 'uploads/original/test.jpg',
        ]);

        Storage::disk('public')->put($task->original_path, 'fake content');

        $optimizationService = Mockery::mock(FileOptimizationService::class);
        $logger = Mockery::mock(OptimizationLogger::class);

        $optimizationService->shouldReceive('optimize')->andReturn(['optimized' => true, 'optimized_size' => 800000, 'compression_ratio' => 0.20, 'size_reduction' => 200000, 'algorithm' => 'JPEG', 'processing_time' => '150']);
        $optimizationService->shouldReceive('supportsWebpConversion')->andReturn(false);

        $logger->shouldReceive('logTaskProcessingStarted')->once();
        $logger->shouldReceive('logTaskCompleted')->once();

        $job = new OptimizeFileJob($task);
        $job->handle($optimizationService, $logger);

        // Just verify the job completed without throwing
        $this->assertTrue(true);
    }

    public function test_handles_job_failure_gracefully(): void
    {
        $task = OptimizationTask::factory()->create([
            'original_path' => 'uploads/original/test.jpg',
        ]);

        Storage::disk('public')->put($task->original_path, 'fake content');

        $optimizationService = Mockery::mock(FileOptimizationService::class);
        $logger = Mockery::mock(OptimizationLogger::class);

        $optimizationService->shouldReceive('optimize')
            ->once()
            ->andReturn(['optimized' => false, 'reason' => 'Test failure']);

        $logger->shouldReceive('logTaskProcessingStarted')->once();
        $logger->shouldReceive('logTaskFailed')->twice();

        $job = new OptimizeFileJob($task);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test failure');

        $job->handle($optimizationService, $logger);
    }

    public function test_failed_method_updates_task_status(): void
    {
        $task = OptimizationTask::factory()->create();

        $job = new OptimizeFileJob($task);
        $exception = new \Exception('Test failure');

        $job->failed($exception);

        $task->refresh();
        $this->assertEquals('failed', $task->status);
        $this->assertStringContainsString('Test failure', $task->error_message);
    }
} 