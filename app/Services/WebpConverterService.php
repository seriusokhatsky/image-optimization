<?php

/**
 * WebP Converter Service
 * 
 * Handles conversion of various image formats to WebP using Cwebp optimizer.
 */

namespace App\Services;

use App\Traits\CalculatesOptimizationMetrics;
use Spatie\ImageOptimizer\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Illuminate\Support\Facades\File;

/**
 * Service for converting images to WebP format
 */
class WebpConverterService
{
    use CalculatesOptimizationMetrics;
    
    /**
     * Supported MIME types for WebP conversion
     */
    private array $_supportedMimeTypes = [
        'image/jpeg',
        'image/png', 
        'image/tiff',
        'image/webp'
    ];

    /**
     * Create a WebP version of an image file.
     *
     * @param  string  $sourcePath  Source file path
     * @param  string  $outputPath  Output WebP file path
     * @param  int     $quality     WebP quality (1-100)
     * 
     * @return array Conversion result data
     */
    public function convertToWebp(string $sourcePath, string $outputPath, int $quality = 80): array
    {
        $startTime = microtime(true);

        try {
            // Validate source file exists
            if (!File::exists($sourcePath)) {
                throw new \Exception("Source file not found: {$sourcePath}");
            }

            // Get MIME type
            $mimeType = File::mimeType($sourcePath);
            if (!$this->canConvertToWebp($mimeType)) {
                throw new \Exception("Unsupported file type for WebP conversion: {$mimeType}");
            }

            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!File::isDirectory($outputDir)) {
                File::makeDirectory($outputDir, 0755, true);
            }

            // Copy source to output path for WebP processing
            $tempWebpPath = $outputPath;
            File::copy($sourcePath, $tempWebpPath);

            // Create custom WebP optimizer chain
            $webpOptimizer = new class(['-q', (string)$quality]) extends Cwebp {
                private array $_supportedMimeTypes = ['image/jpeg', 'image/png', 'image/tiff', 'image/webp'];
                
                public function canHandle(Image $image): bool
                {
                    // Allow handling any supported MIME type for conversion to WebP
                    return in_array($image->mime(), $this->_supportedMimeTypes);
                }
            };

            $optimizerChain = (new OptimizerChain())->addOptimizer($webpOptimizer);

            // Get original file size
            $originalSize = File::size($sourcePath);

            // Convert to WebP
            $optimizerChain->optimize($tempWebpPath);

            // Get WebP file size
            $webpSize = File::size($tempWebpPath);

            // Check if WebP conversion actually reduced file size
            if ($webpSize >= $originalSize) {
                // WebP is larger or same size - still provide it but flag the issue
                return [
                    'success' => true,
                    'original_size' => $originalSize,
                    'webp_size' => $webpSize,
                    'size_reduction' => $originalSize - $webpSize, // Will be negative
                    'compression_ratio' => $this->calculateCompressionRatio($originalSize, $webpSize), // Will be negative
                    'processing_time' => $this->calculateProcessingTime($startTime),
                    'webp_path' => $outputPath,
                    'size_increase_warning' => true,
                    'reason' => 'WebP conversion increased file size - may not be beneficial for this image'
                ];
            }

            return [
                'success' => true,
                'original_size' => $originalSize,
                'webp_size' => $webpSize,
                'size_reduction' => $originalSize - $webpSize,
                'compression_ratio' => $this->calculateCompressionRatio($originalSize, $webpSize),
                'processing_time' => $this->calculateProcessingTime($startTime),
                'webp_path' => $outputPath
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => $this->calculateProcessingTime($startTime),
                'webp_path' => null
            ];
        }
    }

    /**
     * Check if a MIME type can be converted to WebP.
     *
     * @param string $mimeType File MIME type
     * @return bool Whether the MIME type is supported
     */
    public function canConvertToWebp(string $mimeType): bool
    {
        return in_array($mimeType, $this->_supportedMimeTypes);
    }



    /**
     * Generate WebP file path for storage.
     *
     * @param string $originalPath Original file path
     * @return string WebP file path
     */
    public function generateWebpPath(string $originalPath): string
    {
        $directory = 'uploads/webp';
        $filename = basename($originalPath);
        
        return $directory . '/' . $filename . '.webp';
    }
} 