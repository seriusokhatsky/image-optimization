<?php

namespace Tests\Feature;

use App\Services\FileOptimizationService;
use Tests\TestCase;

class FileOptimizationServiceTest extends TestCase
{
    protected FileOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FileOptimizationService::class);
    }

    public function test_detects_supported_file_types(): void
    {
        $this->assertTrue($this->service->isSupported('jpg'));
        $this->assertTrue($this->service->isSupported('jpeg'));
        $this->assertTrue($this->service->isSupported('png'));
        $this->assertTrue($this->service->isSupported('webp'));
        $this->assertTrue($this->service->isSupported('gif'));
        $this->assertTrue($this->service->isSupported('svg'));
    }

    public function test_rejects_unsupported_file_types(): void
    {
        $this->assertFalse($this->service->isSupported('txt'));
        $this->assertFalse($this->service->isSupported('pdf'));
        $this->assertFalse($this->service->isSupported('doc'));
        $this->assertFalse($this->service->isSupported('zip'));
    }

    public function test_calculates_metrics_correctly(): void
    {
        $originalSize = 1000;
        $optimizedSize = 800;

        $metrics = $this->service->calculateMetrics($originalSize, $optimizedSize);

        $this->assertEquals(20.0, $metrics['compression_ratio']);
        $this->assertEquals(200, $metrics['size_reduction']);
    }

    public function test_handles_no_compression_scenario(): void
    {
        $originalSize = 1000;
        $optimizedSize = 1000;

        $metrics = $this->service->calculateMetrics($originalSize, $optimizedSize);

        $this->assertEquals(0.0, $metrics['compression_ratio']);
        $this->assertEquals(0, $metrics['size_reduction']);
    }

    public function test_handles_size_increase_scenario(): void
    {
        $originalSize = 1000;
        $optimizedSize = 1200;

        $metrics = $this->service->calculateMetrics($originalSize, $optimizedSize);

        $this->assertEquals(-20.0, $metrics['compression_ratio']);
        $this->assertEquals(-200, $metrics['size_reduction']);
    }

    public function test_supports_webp_conversion_for_appropriate_formats(): void
    {
        $this->assertTrue($this->service->supportsWebpConversion('image/jpeg'));
        $this->assertTrue($this->service->supportsWebpConversion('image/png'));
        $this->assertFalse($this->service->supportsWebpConversion('image/gif'));
    }

    public function test_gets_supported_types_list(): void
    {
        $supportedTypes = $this->service->getSupportedTypes();
        
        $this->assertIsArray($supportedTypes);
        $this->assertArrayHasKey('jpg', $supportedTypes);
        $this->assertArrayHasKey('jpeg', $supportedTypes);
        $this->assertArrayHasKey('png', $supportedTypes);
        $this->assertArrayHasKey('webp', $supportedTypes);
    }

    public function test_handles_edge_case_zero_sizes(): void
    {
        $metrics = $this->service->calculateMetrics(0, 0);
        
        $this->assertEquals(0.0, $metrics['compression_ratio']);
        $this->assertEquals(0, $metrics['size_reduction']);
    }

    public function test_handles_large_file_sizes(): void
    {
        $originalSize = 50000000; // 50MB
        $optimizedSize = 25000000; // 25MB

        $metrics = $this->service->calculateMetrics($originalSize, $optimizedSize);

        $this->assertEquals(50.0, $metrics['compression_ratio']);
        $this->assertEquals(25000000, $metrics['size_reduction']);
    }

    public function test_returns_comprehensive_supported_types_info(): void
    {
        $supportedTypes = $this->service->getSupportedTypes();
        
        $this->assertArrayHasKey('jpg', $supportedTypes);
        $this->assertArrayHasKey('png', $supportedTypes);
        $this->assertStringContainsString('MozJPEG', $supportedTypes['jpg']);
        $this->assertStringContainsString('Pngquant', $supportedTypes['png']);
    }
} 