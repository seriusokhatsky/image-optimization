<?php

describe('Application Health', function () {
    it('loads the application successfully', function () {
        expect(true)->toBe(true);
    });

    it('has all required routes registered', function () {
        $routes = collect(app('router')->getRoutes())->pluck('uri')->toArray();
        
        expect($routes)->toContain('/');
        expect($routes)->toContain('api/optimize/submit');
        expect($routes)->toContain('api/optimize/status/{taskId}');
        expect($routes)->toContain('api/optimize/download/{taskId}');
        expect($routes)->toContain('demo/upload');
        expect($routes)->toContain('health');
    });

    it('has all required services registered', function () {
        // Services are instantiated on demand, so we just check if they can be resolved
        expect(app()->make('App\Services\FileOptimizationService'))->toBeInstanceOf('App\Services\FileOptimizationService');
    });
});
