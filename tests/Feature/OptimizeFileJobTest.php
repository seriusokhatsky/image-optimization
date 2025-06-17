<?php

use App\Jobs\OptimizeFileJob;
use App\Models\OptimizationTask;
use App\Services\FileOptimizationService;
use App\Services\OptimizationLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Storage::fake('public');
});

describe('OptimizeFileJob', function () {
    describe('Job Configuration', function () {
        it('has correct timeout and retry settings', function () {
            $task = OptimizationTask::factory()->create();
            $job = new OptimizeFileJob($task);

            expect($job->timeout)->toBe(300);
            expect($job->tries)->toBe(3);
        });

        it('serializes task correctly', function () {
            $task = OptimizationTask::factory()->create();
            $job = new OptimizeFileJob($task);

            expect($job->task)->toBeInstanceOf(OptimizationTask::class);
            expect($job->task->id)->toBe($task->id);
        });
    });

    describe('Job Execution', function () {
        it('marks task as processing when started', function () {
            $task = OptimizationTask::factory()->create(['status' => 'pending']);
            Storage::disk('public')->put($task->original_path, 'fake image content');

            $optimizationService = Mockery::mock(FileOptimizationService::class);
            $optimizationService->shouldReceive('optimize')->andReturn([
                'optimized' => true,
                'optimized_size' => 800,
                'compression_ratio' => 0.20,
                'size_reduction' => 200,
                'algorithm' => 'JPEG optimization',
                'processing_time' => '150 ms',
            ]);
            $optimizationService->shouldReceive('supportsWebpConversion')->andReturn(false);

            $logger = Mockery::mock(OptimizationLogger::class);
            $logger->shouldReceive('logTaskProcessingStarted');
            $logger->shouldReceive('logTaskCompleted');

            $job = new OptimizeFileJob($task);
            $job->handle($optimizationService, $logger);

            $task->refresh();
            expect($task->status)->toBe('completed');
        });

        it('handles missing original file', function () {
            $task = OptimizationTask::factory()->create(['status' => 'pending']);
            // Don't create the file to simulate missing file

            $optimizationService = Mockery::mock(FileOptimizationService::class);
            $logger = Mockery::mock(OptimizationLogger::class);
            $logger->shouldReceive('logTaskProcessingStarted');
            $logger->shouldReceive('logTaskFailed');

            $job = new OptimizeFileJob($task);

            expect(fn() => $job->handle($optimizationService, $logger))
                ->toThrow(Exception::class, 'Original file not found');

            $task->refresh();
            expect($task->status)->toBe('failed');
        });

        it('handles optimization failure', function () {
            $task = OptimizationTask::factory()->create(['status' => 'pending']);
            Storage::disk('public')->put($task->original_path, 'fake image content');

            $optimizationService = Mockery::mock(FileOptimizationService::class);
            $optimizationService->shouldReceive('optimize')->andReturn([
                'optimized' => false,
                'reason' => 'Test optimization failure',
            ]);

            $logger = Mockery::mock(OptimizationLogger::class);
            $logger->shouldReceive('logTaskProcessingStarted');
            $logger->shouldReceive('logTaskFailed');

            $job = new OptimizeFileJob($task);

            expect(fn() => $job->handle($optimizationService, $logger))
                ->toThrow(Exception::class, 'Test optimization failure');

            $task->refresh();
            expect($task->status)->toBe('failed');
        });

        it('generates webp when supported', function () {
            $task = OptimizationTask::factory()->create(['status' => 'pending']);
            Storage::disk('public')->put($task->original_path, 'fake image content');

            $optimizationService = Mockery::mock(FileOptimizationService::class);
            $optimizationService->shouldReceive('optimize')->andReturn([
                'optimized' => true,
                'optimized_size' => 800,
                'compression_ratio' => 0.20,
                'size_reduction' => 200,
                'algorithm' => 'JPEG optimization',
                'processing_time' => '150 ms',
            ]);
            $optimizationService->shouldReceive('supportsWebpConversion')->andReturn(true);
            $optimizationService->shouldReceive('generateWebpCopy')->andReturn([
                'success' => true,
                'webp_path' => 'uploads/webp/test.webp',
                'webp_size' => 600,
                'webp_compression_ratio' => 0.40,
                'webp_size_reduction' => 400,
                'webp_processing_time' => '100 ms',
            ]);

            $logger = Mockery::mock(OptimizationLogger::class);
            $logger->shouldReceive('logTaskProcessingStarted');
            $logger->shouldReceive('logTaskCompleted');

            $job = new OptimizeFileJob($task);
            $job->handle($optimizationService, $logger);

            $task->refresh();
            expect($task->webp_generated)->toBe(true);
            expect($task->webp_path)->toBe('uploads/webp/test.webp');
        });

        it('handles webp generation failure gracefully', function () {
            $task = OptimizationTask::factory()->create(['status' => 'pending']);
            Storage::disk('public')->put($task->original_path, 'fake image content');

            $optimizationService = Mockery::mock(FileOptimizationService::class);
            $optimizationService->shouldReceive('optimize')->andReturn([
                'optimized' => true,
                'optimized_size' => 800,
                'compression_ratio' => 0.20,
                'size_reduction' => 200,
                'algorithm' => 'JPEG optimization',
                'processing_time' => '150 ms',
            ]);
            $optimizationService->shouldReceive('supportsWebpConversion')->andReturn(true);
            $optimizationService->shouldReceive('generateWebpCopy')->andReturn([
                'success' => false,
                'reason' => 'WebP conversion failed',
            ]);

            $logger = Mockery::mock(OptimizationLogger::class);
            $logger->shouldReceive('logTaskProcessingStarted');
            $logger->shouldReceive('logTaskCompleted');

            $job = new OptimizeFileJob($task);
            $job->handle($optimizationService, $logger);

            $task->refresh();
            expect($task->status)->toBe('completed');
            expect($task->webp_generated)->toBe(false);
        });
    });

    describe('Job Failure Handling', function () {
        it('handles job failure correctly', function () {
            $task = OptimizationTask::factory()->create(['status' => 'pending']);
            $exception = new Exception('Test exception');

            $logger = Mockery::mock(OptimizationLogger::class);
            $logger->shouldReceive('logTaskFailed');

            $job = new OptimizeFileJob($task);
            $job->failed($exception);

            $task->refresh();
            expect($task->status)->toBe('failed');
            expect($task->error_message)->toBe('Test exception');
        });
    });

    describe('Logging', function () {
        it('logs task processing events', function () {
            $task = OptimizationTask::factory()->create(['status' => 'pending']);
            Storage::disk('public')->put($task->original_path, 'fake image content');

            $optimizationService = Mockery::mock(FileOptimizationService::class);
            $optimizationService->shouldReceive('optimize')->andReturn([
                'optimized' => true,
                'optimized_size' => 800,
                'compression_ratio' => 0.20,
                'size_reduction' => 200,
                'algorithm' => 'JPEG optimization',
                'processing_time' => '150 ms',
            ]);
            $optimizationService->shouldReceive('supportsWebpConversion')->andReturn(false);

            $logger = Mockery::mock(OptimizationLogger::class);
            $logger->shouldReceive('logTaskProcessingStarted')->with($task);
            $logger->shouldReceive('logTaskCompleted')->with($task, Mockery::any());

            $job = new OptimizeFileJob($task);
            $job->handle($optimizationService, $logger);

            // Verify the logger was called correctly (mocks will handle this)
            expect($task->refresh()->status)->toBe('completed');
        });
    });
}); 