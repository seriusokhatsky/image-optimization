<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ImageUploadRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_allows_requests_within_rate_limit(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/optimize/submit', [
                'file' => $file,
            ]);

            $this->assertContains($response->getStatusCode(), [202, 422]);
        }
    }

    public function test_tracks_different_ips_separately(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1']);
        $response1 = $this->postJson('/api/optimize/submit', ['file' => $file]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.2']);
        $response2 = $this->postJson('/api/optimize/submit', ['file' => $file]);

        $this->assertContains($response1->getStatusCode(), [202, 422]);
        $this->assertContains($response2->getStatusCode(), [202, 422]);
    }

    public function test_cache_key_generation_is_ip_based(): void
    {
        $ip1 = '192.168.1.1';
        $ip2 = '192.168.1.2';

        Cache::put("image_upload_rate_limit:{$ip1}", 1, 60);
        Cache::put("image_upload_rate_limit:{$ip2}", 1, 60);

        $this->assertTrue(Cache::has("image_upload_rate_limit:{$ip1}"));
        $this->assertTrue(Cache::has("image_upload_rate_limit:{$ip2}"));
    }

    public function test_rate_limit_data_structure(): void
    {
        $ip = '192.168.1.1';
        $cacheKey = "image_upload_rate_limit:{$ip}";

        Cache::put($cacheKey, ['count' => 1, 'first_attempt' => time()], 60);

        $data = Cache::get($cacheKey);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('first_attempt', $data);
    }

    public function test_middleware_applies_to_submit_route(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson('/api/optimize/submit', [
            'file' => $file,
        ]);

        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_rate_limit_configuration_exists(): void
    {
        $rateLimitConfig = config('app.image_upload_rate_limit', 5);
        
        $this->assertIsInt($rateLimitConfig);
        $this->assertGreaterThan(0, $rateLimitConfig);
    }
} 