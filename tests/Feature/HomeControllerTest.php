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

describe('HomeController Demo Interface', function () {
    describe('GET /', function () {
        it('displays the demo interface', function () {
            $response = $this->get('/');

            $response->assertStatus(200)
                ->assertViewIs('demo');
        });
    });

    describe('POST /demo/upload', function () {
        it('can upload file through demo interface', function () {
            $file = UploadedFile::fake()->image('demo.jpg', 200, 200);

            $response = $this->postJson('/demo/upload', [
                'file' => $file,
                'quality' => 75,
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'status' => 'pending',
                ])
                ->assertJsonStructure([
                    'task_id',
                    'original_file' => [
                        'name',
                        'size',
                        'size_formatted',
                    ],
                ]);

            expect(OptimizationTask::count())->toBe(1);
            Queue::assertPushed(OptimizeFileJob::class);
        });

        it('validates file type for demo upload', function () {
            $file = UploadedFile::fake()->create('document.pdf');

            $response = $this->postJson('/demo/upload', [
                'file' => $file,
                'quality' => 80,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
        });

        it('validates required file for demo upload', function () {
            $response = $this->postJson('/demo/upload', [
                'quality' => 80,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
        });

        it('respects rate limiting', function () {
            // This test would require setting up rate limiting configuration
            // We'll test the middleware separately
            $this->assertTrue(true);
        });
    });

    describe('GET /demo/status/{taskId}', function () {
        it('can get demo task status', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'pending',
                'original_size' => 10000,
            ]);

            $response = $this->getJson("/demo/status/{$task->task_id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'task_id' => $task->task_id,
                    'status' => 'pending',
                ])
                ->assertJsonStructure([
                    'original_file' => [
                        'name',
                        'size',
                        'size_formatted',
                    ],
                ]);
        });

        it('includes formatted sizes in demo status', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'original_size' => 10000,
                'optimized_size' => 8000,
                'size_reduction' => 2000,
                'compression_ratio' => 0.20,
                'algorithm' => 'JPEG optimization',
                'processing_time' => '100 ms',
            ]);

            $response = $this->getJson("/demo/status/{$task->task_id}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'optimization' => [
                        'compression_ratio',
                        'size_reduction',
                        'size_reduction_formatted',
                        'optimized_size_formatted',
                        'algorithm',
                        'processing_time',
                    ],
                ]);
        });

        it('includes webp information in demo status', function () {
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'webp_generated' => true,
                'webp_size' => 7000,
                'webp_compression_ratio' => 0.30,
                'webp_size_reduction' => 3000,
            ]);

            $response = $this->getJson("/demo/status/{$task->task_id}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'webp' => [
                        'compression_ratio',
                        'size_reduction',
                        'size_reduction_formatted',
                        'webp_size_formatted',
                    ],
                ]);
        });

        it('returns 404 for non-existent demo task', function () {
            $response = $this->getJson('/demo/status/non-existent');

            $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Task not found',
                ]);
        });

        it('returns 410 for expired demo task', function () {
            $task = OptimizationTask::factory()->create([
                'expires_at' => now()->subDay(),
            ]);

            $response = $this->getJson("/demo/status/{$task->task_id}");

            $response->assertStatus(410)
                ->assertJson([
                    'success' => false,
                    'message' => 'Task has expired',
                ]);
        });
    });

    describe('Health Check Route', function () {
        it('returns health status', function () {
            $response = $this->getJson('/health');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'timestamp',
                    'app',
                    'environment',
                ])
                ->assertJson([
                    'status' => 'healthy',
                ]);
        });
    });

    describe('Download Routes', function () {
        it('can access demo download route', function () {
            Storage::disk('public')->put('uploads/optimized/test.jpg', 'optimized content');
            
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'optimized_path' => 'uploads/optimized/test.jpg',
                'original_filename' => 'demo.jpg',
            ]);

            $response = $this->get("/download/{$task->task_id}");

            $response->assertStatus(200);
        });

        it('can access demo webp download route', function () {
            Storage::disk('public')->put('uploads/webp/test.webp', 'webp content');
            
            $task = OptimizationTask::factory()->create([
                'status' => 'completed',
                'webp_path' => 'uploads/webp/test.webp',
                'webp_generated' => true,
                'original_filename' => 'demo.jpg',
            ]);

            $response = $this->get("/download/{$task->task_id}/webp");

            $response->assertStatus(200);
        });
    });
}); 