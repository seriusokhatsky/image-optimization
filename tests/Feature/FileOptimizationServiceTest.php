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
        $this->assertTrue($this->service->isSupported('image/jpeg'));
        $this->assertTrue($this->service->isSupported('image/png'));
        $this->assertTrue($this->service->isSupported('image/webp'));
        $this->assertTrue($this->service->isSupported('image/gif'));
    }

    public function test_rejects_unsupported_file_types(): void
    {
        $this->assertFalse($this->service->isSupported('txt'));
        $this->assertFalse($this->service->isSupported('pdf'));
        $this->assertFalse($this->service->isSupported('doc'));
        $this->assertFalse($this->service->isSupported('zip'));
        $this->assertFalse($this->service->isSupported('image/svg+xml'));
    }

    public function test_supports_webp_conversion_for_appropriate_formats(): void
    {
        $this->assertTrue($this->service->supportsWebpConversion('image/jpeg'));
        $this->assertTrue($this->service->supportsWebpConversion('image/png'));
        $this->assertFalse($this->service->supportsWebpConversion('image/gif'));
    }



} 