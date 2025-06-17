<?php

use App\Models\OptimizationTask;
use App\Jobs\OptimizeFileJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
});

describe('OptimizeController API', function () {
    describe('POST /api/optimize/submit', function () {
        it('can submit a file for optimization', function () {
            $file = UploadedFile::fake()->image('test.jpg', 100, 100);

            $response = $this->postJson('/api/optimize/submit', [
                'file' => $file,
                'quality' => 80,
            ]);

            $response->assertStatus(202)
                ->assertJson([
                    'success' => true,
                    'message' => 'File uploaded successfully. Optimization in progress.',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'task_id',
                        'status',
                        'original_file' => [
                            'name',
                            'size',
                        ],
                        'estimated_completion',
                    ],
                ]);

            expect(OptimizationTask::count())->toBeGreaterThanOrEqual(1);
            Queue::assertPushed(OptimizeFileJob::class);
        });

        it('validates required file field', function () {
            $response = $this->postJson('/api/optimize/submit', [
                'quality' => 80,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
        });

        it('validates file size limit', function () {
            $file = UploadedFile::fake()->create('large.jpg', 12000); // 12MB

            $response = $this->postJson('/api/optimize/submit', [
                'file' => $file,
                'quality' => 80,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
        });

        it('validates quality parameter', function () {
            $file = UploadedFile::fake()->image('test.jpg', 100, 100);

            $response = $this->postJson('/api/optimize/submit', [
                'file' => $file,
                'quality' => 150, // Invalid quality
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['quality']);
        });

                it('accepts file without quality parameter', function () {
            $file = UploadedFile::fake()->image('test.jpg', 100, 100);
            
            $response = $this->postJson('/api/optimize/submit', [
                'file' => $file,
            ]);

            $response->assertStatus(202);
            
            // Get the most recent task
            $task = OptimizationTask::latest()->first();
            expect($task->quality)->toBe(80); // Default quality
        });
    });

    describe('GET /api/optimize/status/{taskId}', function () {
        it('can get status of pending task', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'pending',
            ]);

            $response = $this->getJson("/api/optimize/status/{$task->task_id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'task_id' => $task->task_id,
                        'status' => 'pending',
                        'original_file' => [
                            'name' => $task->original_filename,
                            'size' => $task->original_size,
                        ],
                    ],
                ]);
        });

        it('can get status of processing task', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $response = $this->getJson("/api/optimize/status/{$task->task_id}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'task_id',
                        'status',
                        'started_at',
                        'original_file',
                    ],
                ]);
        });

        it('can get status of completed task', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'completed_at' => now(),
                'optimized_size' => 5000,
                'compression_ratio' => 0.50,
                'size_reduction' => 5000,
                'algorithm' => 'JPEG optimization',
                'processing_time' => '150 ms',
            ]);

            $response = $this->getJson("/api/optimize/status/{$task->task_id}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'task_id',
                        'status',
                        'optimization' => [
                            'compression_ratio',
                            'size_reduction',
                            'algorithm',
                            'processing_time',
                            'optimized_size',
                        ],
                        'completed_at',
                        'download_url',
                    ],
                ]);
        });

        it('can get status of completed task with webp', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'completed_at' => now(),
                'webp_generated' => true,
                'webp_size' => 4000,
                'webp_compression_ratio' => 0.60,
                'webp_size_reduction' => 6000,
            ]);

            $response = $this->getJson("/api/optimize/status/{$task->task_id}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'webp' => [
                            'compression_ratio',
                            'size_reduction',
                            'processing_time',
                            'webp_size',
                        ],
                        'webp_download_url',
                    ],
                ]);
        });

        it('can get status of failed task', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'failed',
                'error_message' => 'Invalid file format',
                'completed_at' => now(),
            ]);

            $response = $this->getJson("/api/optimize/status/{$task->task_id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'task_id' => $task->task_id,
                        'status' => 'failed',
                        'error' => 'Invalid file format',
                    ],
                ]);
        });

        it('returns 404 for non-existent task', function () {
            $response = $this->getJson('/api/optimize/status/non-existent-id');

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Task not found',
                ]);
        });

        it('returns 410 for expired task', function () {
            $task = OptimizationTask::factory()->create([
                'expires_at' => now()->subHour(),
            ]);

            $response = $this->getJson("/api/optimize/status/{$task->task_id}");

            $response->assertStatus(410)
                ->assertJson([
                    'success' => false,
                    'message' => 'Task has expired',
                ]);
        });
    });

    describe('GET /api/optimize/download/{taskId}', function () {
        it('can download completed task file', function () {
            Storage::disk('public')->put('uploads/optimized/test.jpg', 'fake optimized content');
            
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'optimized_path' => 'uploads/optimized/test.jpg',
                'original_filename' => 'test.jpg',
            ]);

            $response = $this->get("/api/optimize/download/{$task->task_id}");

            $response->assertStatus(200);
            expect($response->headers->get('content-disposition'))->toContain('test-optimized.jpg');
        });

        it('returns 404 for non-existent task', function () {
            $response = $this->getJson('/api/optimize/download/non-existent-id');

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Task not found or not completed',
                ]);
        });

        it('returns 404 for non-completed task', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'pending',
            ]);

            $response = $this->getJson("/api/optimize/download/{$task->task_id}");

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Task not found or not completed',
                ]);
        });

        it('returns 410 for expired task', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'expires_at' => now()->subHour(),
            ]);

            $response = $this->getJson("/api/optimize/download/{$task->task_id}");

            $response->assertStatus(410)
                ->assertJson([
                    'success' => false,
                    'message' => 'Task has expired',
                ]);
        });

        it('returns 404 when optimized file not found', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'optimized_path' => 'uploads/optimized/missing.jpg',
            ]);

            $response = $this->getJson("/api/optimize/download/{$task->task_id}");

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Optimized file not found',
                ]);
        });
    });

    describe('GET /api/optimize/download/{taskId}/webp', function () {
        it('can download webp file', function () {
            Storage::disk('public')->put('uploads/webp/test.jpg.webp', 'fake webp content');
            
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'webp_path' => 'uploads/webp/test.jpg.webp',
                'webp_generated' => true,
                'original_filename' => 'test.jpg',
            ]);

            $response = $this->get("/api/optimize/download/{$task->task_id}/webp");

            $response->assertStatus(200);
            expect($response->headers->get('content-disposition'))->toContain('test.jpg.webp');
        });

        it('returns 404 for task without webp', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'webp_generated' => false,
            ]);

            $response = $this->getJson("/api/optimize/download/{$task->task_id}/webp");

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Task not found, not completed, or WebP not generated',
                ]);
        });
    });
}); 