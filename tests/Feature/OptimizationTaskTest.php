<?php

use App\Models\OptimizationTask;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('OptimizationTask Model', function () {
    describe('Creation and Basic Properties', function () {
        it('can create optimization task', function () {
            $task = OptimizationTask::factory()->create();

            expect($task)->toBeInstanceOf(OptimizationTask::class);
            expect($task->task_id)->not->toBeNull();
            expect($task->expires_at)->not->toBeNull();
        });

        it('automatically generates task_id on creation', function () {
            $task = OptimizationTask::create([
                'original_filename' => 'test.jpg',
                'original_path' => 'uploads/test.jpg',
                'original_size' => 1000,
                'quality' => 80,
            ]);

            expect($task->task_id)->not->toBeNull();
            expect(strlen($task->task_id))->toBe(36); // UUID length
        });

        it('automatically sets expiration date', function () {
            $task = OptimizationTask::create([
                'original_filename' => 'test.jpg',
                'original_path' => 'uploads/test.jpg',
                'original_size' => 1000,
                'quality' => 80,
            ]);

            expect($task->expires_at)->not->toBeNull();
            expect($task->expires_at->isAfter(now()))->toBe(true);
        });
    });

    describe('Status Management', function () {
        it('can mark task as processing', function () {
            $task = OptimizationTask::factory()->create(['status' => 'pending']);

            $task->markAsProcessing();

            expect($task->status)->toBe('processing');
            expect($task->started_at)->not->toBeNull();
        });

        it('can mark task as completed', function () {
            $task = OptimizationTask::factory()->create(['status' => 'processing']);

            $optimizationData = [
                'optimized_path' => 'uploads/optimized/test.jpg',
                'optimized_size' => 800,
                'compression_ratio' => 0.20,
                'size_reduction' => 200,
                'algorithm' => 'JPEG optimization',
                'processing_time' => '150 ms',
            ];

            $task->markAsCompleted($optimizationData);

            expect($task->status)->toBe('completed');
            expect($task->optimized_path)->toBe('uploads/optimized/test.jpg');
            expect($task->optimized_size)->toBe(800);
            expect((float)$task->compression_ratio)->toBe(0.20);
            expect($task->completed_at)->not->toBeNull();
        });

        it('can mark task as failed', function () {
            $task = OptimizationTask::factory()->create(['status' => 'processing']);

            $task->markAsFailed('Test error message');

            expect($task->status)->toBe('failed');
            expect($task->error_message)->toBe('Test error message');
            expect($task->completed_at)->not->toBeNull();
        });
    });

    describe('WebP Management', function () {
        it('can update webp data', function () {
            $task = OptimizationTask::factory()->create();

            $webpData = [
                'webp_path' => 'uploads/webp/test.webp',
                'webp_size' => 600,
                'webp_compression_ratio' => 0.40,
                'webp_size_reduction' => 400,
                'webp_processing_time' => '100 ms',
                'webp_generated' => true,
            ];

            $task->updateWebpData($webpData);

            expect($task->webp_path)->toBe('uploads/webp/test.webp');
            expect($task->webp_size)->toBe(600);
            expect($task->webp_generated)->toBe(true);
        });
    });

    describe('Expiration Logic', function () {
        it('detects expired tasks', function () {
            $task = OptimizationTask::factory()->expired()->create();

            expect($task->isExpired())->toBe(true);
        });

        it('detects non-expired tasks', function () {
            $task = OptimizationTask::factory()->create();

            expect($task->isExpired())->toBe(false);
        });

        it('can query expired tasks', function () {
            // Clear any existing tasks first
            OptimizationTask::truncate();
            
            $expiredTask = OptimizationTask::factory()->expired()->create();
            $activeTask = OptimizationTask::factory()->create();

            $expiredTasks = OptimizationTask::expired()->get();

            expect($expiredTasks)->toHaveCount(1);
            expect($expiredTasks->first()->id)->toBe($expiredTask->id);
        });
    });

    describe('File Name Generation', function () {
        it('generates optimized filename correctly', function () {
            $task = OptimizationTask::factory()->create([
                'original_filename' => 'my_photo.jpeg',
            ]);

            $optimizedFilename = $task->getOptimizedFilename();

            expect($optimizedFilename)->toBe('my_photo-optimized.jpeg');
        });

        it('handles filename without extension', function () {
            $task = OptimizationTask::factory()->create([
                'original_filename' => 'photo',
            ]);

            $optimizedFilename = $task->getOptimizedFilename();

            expect($optimizedFilename)->toBe('photo-optimized');
        });

        it('generates webp filename correctly', function () {
            $task = OptimizationTask::factory()->create([
                'original_filename' => 'photo.jpg',
            ]);

            $webpFilename = $task->getWebpFilename();

            expect($webpFilename)->toBe('photo.jpg.webp');
        });
    });

    describe('Path Generation', function () {
        it('generates optimized path correctly', function () {
            $task = OptimizationTask::factory()->create([
                'original_path' => 'uploads/original/uuid-123.jpg',
            ]);

            $optimizedPath = $task->generateOptimizedPath();

            expect($optimizedPath)->toBe('uploads/optimized/uuid-123.jpg');
        });

        it('generates webp path correctly', function () {
            $task = OptimizationTask::factory()->create([
                'original_path' => 'uploads/original/uuid-123.jpg',
            ]);

            $webpPath = $task->generateWebpPath();

            expect($webpPath)->toBe('uploads/webp/uuid-123.jpg.webp');
        });
    });

    describe('Data Casting', function () {
        it('casts timestamps correctly', function () {
            $task = OptimizationTask::factory()->completed()->create();

            expect($task->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
            expect($task->completed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
            expect($task->expires_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('casts compression ratio as decimal', function () {
            $task = OptimizationTask::factory()->completed()->create([
                'compression_ratio' => 0.2567,
            ]);

            expect((float)$task->compression_ratio)->toBe(0.26);
        });

        it('casts webp_generated as boolean', function () {
            $task = OptimizationTask::factory()->withWebp()->create();

            expect($task->webp_generated)->toBe(true);
            expect(is_bool($task->webp_generated))->toBe(true);
        });
    });
}); 