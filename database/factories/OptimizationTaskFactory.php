<?php

namespace Database\Factories;

use App\Models\OptimizationTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class OptimizationTaskFactory extends Factory
{
    protected $model = OptimizationTask::class;

    public function definition(): array
    {
        return [
            'task_id' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'pending',
            'original_filename' => $this->faker->word . '.jpg',
            'original_path' => 'uploads/original/' . (string) \Illuminate\Support\Str::uuid() . '.jpg',
            'optimized_path' => null,
            'webp_path' => null,
            'original_size' => $this->faker->numberBetween(100000, 5000000),
            'optimized_size' => null,
            'webp_size' => null,
            'quality' => 80, // Default quality - matches database default
            'compression_ratio' => null,
            'webp_compression_ratio' => null,
            'size_reduction' => null,
            'webp_size_reduction' => null,
            'algorithm' => null,
            'processing_time' => null,
            'webp_processing_time' => null,
            'webp_generated' => false,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $originalSize = $attributes['original_size'];
            $optimizedSize = $this->faker->numberBetween(50000, $originalSize);
            $sizeReduction = $originalSize - $optimizedSize;
            
            return [
                'status' => 'completed',
                'optimized_path' => 'uploads/optimized/' . (string) \Illuminate\Support\Str::uuid() . '.jpg',
                'optimized_size' => $optimizedSize,
                'compression_ratio' => round($sizeReduction / $originalSize, 2),
                'size_reduction' => $sizeReduction,
                'algorithm' => 'JPEG optimization with MozJPEG',
                'processing_time' => $this->faker->numberBetween(50, 500) . ' ms',
                'started_at' => now()->subMinutes(2),
                'completed_at' => now(),
            ];
        });
    }

    public function withWebp(): static
    {
        return $this->state(function (array $attributes) {
            $originalSize = $attributes['original_size'];
            $webpSize = $this->faker->numberBetween(30000, $originalSize * 0.8);
            $webpSizeReduction = $originalSize - $webpSize;
            
            return [
                'webp_path' => 'uploads/webp/' . (string) \Illuminate\Support\Str::uuid() . '.webp',
                'webp_size' => $webpSize,
                'webp_compression_ratio' => round($webpSizeReduction / $originalSize, 2),
                'webp_size_reduction' => $webpSizeReduction,
                'webp_processing_time' => $this->faker->numberBetween(20, 200) . ' ms',
                'webp_generated' => true,
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $this->faker->sentence(),
            'started_at' => now()->subMinutes(1),
            'completed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }
}  