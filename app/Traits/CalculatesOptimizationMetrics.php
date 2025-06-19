<?php

namespace App\Traits;

trait CalculatesOptimizationMetrics
{
    /**
     * Calculate processing time in milliseconds.
     *
     * @param float $startTime Start time from microtime(true)
     * @return string Processing time in milliseconds with 'ms' suffix
     */
    private function calculateProcessingTime(float $startTime): string
    {
        $endTime = microtime(true);
        $processingTime = round(($endTime - $startTime) * 1000, 2);
        return $processingTime . ' ms';
    }

    /**
     * Calculate compression ratio.
     *
     * @param int $originalSize Original file size
     * @param int $optimizedSize Optimized file size
     * @return float Compression ratio percentage
     */
    private function calculateCompressionRatio(int $originalSize, int $optimizedSize): float
    {
        if ($originalSize === 0) {
            return 0.0;
        }
        
        return round(($originalSize - $optimizedSize) / $originalSize * 100, 2);
    }
} 