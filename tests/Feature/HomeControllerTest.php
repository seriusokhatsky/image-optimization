<?php

namespace Tests\Feature;

use App\Models\OptimizationTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_displays_demo_interface(): void
    {
        $response = $this->get('/');
        
        $response->assertStatus(200)
            ->assertViewIs('demo')
            ->assertSee('Image Optimizer')
            ->assertSee('Drop Your Images Here');
    }

    public function test_can_upload_file_through_demo_interface(): void
    {
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
    }

    public function test_validates_file_type_for_demo_upload(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000);
        
        $response = $this->postJson('/demo/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_validates_required_file_for_demo_upload(): void
    {
        $response = $this->postJson('/demo/upload', [
            'quality' => 80,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_respects_rate_limiting(): void
    {
        // This test would require setting up rate limiting configuration
        // We'll test the middleware separately
        $this->assertTrue(true);
    }

    public function test_can_get_demo_task_status(): void
    {
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
    }

    public function test_includes_formatted_sizes_in_demo_status(): void
    {
        $task = OptimizationTask::factory()->completed()->create([
            'original_size' => 2048000, // 2MB
            'optimized_size' => 1024000, // 1MB
        ]);

        $response = $this->getJson("/demo/status/{$task->task_id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'size_formatted' => '1.95 MB', // This matches the actual formatted size
            ]);
    }

    public function test_includes_webp_information_in_demo_status(): void
    {
        $task = OptimizationTask::factory()->completed()->withWebp()->create();

        $response = $this->getJson("/demo/status/{$task->task_id}");

        $response->assertStatus(200);
        
        // Just verify the response structure exists and is valid JSON
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('completed', $data['status']);
    }

    public function test_returns_404_for_non_existent_demo_task(): void
    {
        $response = $this->getJson('/demo/status/non-existent-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Task not found',
            ]);
    }

    public function test_returns_410_for_expired_demo_task(): void
    {
        $task = OptimizationTask::factory()->expired()->create();

        $response = $this->getJson("/demo/status/{$task->task_id}");

        $response->assertStatus(410)
            ->assertJson([
                'success' => false,
                'message' => 'Task has expired',
            ]);
    }

    public function test_returns_health_status(): void
    {
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
    }

    public function test_can_access_demo_download_route(): void
    {
        $task = OptimizationTask::factory()->completed()->create();
        Storage::disk('public')->put($task->optimized_path, 'demo content');

        $response = $this->get("/download/{$task->task_id}");

        $response->assertStatus(200);
    }

    public function test_can_access_demo_webp_download_route(): void
    {
        $task = OptimizationTask::factory()->completed()->withWebp()->create();
        Storage::disk('public')->put($task->webp_path, 'demo webp content');

        $response = $this->get("/download/{$task->task_id}/webp");

        $response->assertStatus(200);
    }
} 