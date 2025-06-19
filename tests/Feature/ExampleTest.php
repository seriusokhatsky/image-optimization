<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_application_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_has_all_required_routes_registered(): void
    {
        $this->assertTrue($this->routeExists('/'));
        $this->assertTrue($this->routeExists('/demo/upload'));
        $this->assertTrue($this->routeExists('/api/optimize/submit'));
    }

    public function test_has_all_required_services_registered(): void
    {
        $this->assertInstanceOf(
            'App\Services\FileOptimizationService',
            app()->make('App\Services\FileOptimizationService')
        );
    }

    private function routeExists(string $path): bool
    {
        try {
            $response = $this->get($path);
            return $response->getStatusCode() !== 404;
        } catch (\Exception $e) {
            return false;
        }
    }
}
