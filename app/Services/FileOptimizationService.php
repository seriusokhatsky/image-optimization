<?php

namespace App\Services;

use App\Services\Optimizers\MozjpegOptimizer;
use App\Services\WebpConverterService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Svgo;

class FileOptimizationService
{
    private $optimizerChain;
    private WebpConverterService $webpConverter;

    public function __construct()
    {
        // Create the base optimizer chain (we'll set specific optimizers per file type)
        $this->optimizerChain = OptimizerChainFactory::create();
        $this->webpConverter = new WebpConverterService();
    }

    /**
     * Optimize an uploaded file.
     *
     * @param UploadedFile $file The uploaded file to optimize
     * @param string $extension The file extension
     * @param string $originalPath The path where the original file is stored
     * @param int $quality The optimization quality (1-100)
     * @return array Optimization result data
     */
    public function optimize(UploadedFile $file, string $extension, string $originalPath, int $quality = 80): array
    {
        $startTime = microtime(true);
        
        // Get the full system path to the original file
        $originalFilePath = Storage::disk('public')->path($originalPath);
        
        // Check if file type is supported for optimization
        if (!$this->isSupported($extension)) {
            return [
                'algorithm' => 'Generic file compression (not optimized)',
                'processing_time' => '0 ms',
                'optimized' => false,
                'reason' => 'File type not supported for image optimization'
            ];
        }

        try {
            // Get original file size
            $originalSize = filesize($originalFilePath);
            
            // Create optimized file path
            $optimizedFileName = basename($originalPath);
            $optimizedPath = Storage::disk('public')->path('uploads/optimized/' . $optimizedFileName);
            
            // Ensure the optimized directory exists
            $optimizedDir = dirname($optimizedPath);
            if (!is_dir($optimizedDir)) {
                mkdir($optimizedDir, 0755, true);
            }
            
            // Copy original file to optimized location first
            copy($originalFilePath, $optimizedPath);
            
            // Use specific optimizers based on file type
            $mimeType = $file->getMimeType();
            if ($mimeType === 'image/jpeg') {
                $this->optimizerChain->setOptimizers([new MozjpegOptimizer([
                    '-quality', (string)$quality
                ])]);
            } elseif ($mimeType === 'image/webp') {
                $this->optimizerChain->setOptimizers([new Cwebp([
                    '-q', (string)$quality
                ])]);
            } elseif ($mimeType === 'image/png') {
                // For PNG, we use quality differently - Pngquant accepts quality ranges
                $pngQuality = max(1, min(100, $quality));
                $this->optimizerChain->setOptimizers([
                    new Pngquant(['--force', '--quality=' . max(1, $pngQuality - 20) . '-' . $pngQuality]),
                    new Optipng(['-i0', '-o2', '-quiet'])
                ]);
            } elseif ($mimeType === 'image/gif') {
                // GIF optimization doesn't use quality in the same way, but we can adjust optimization level
                $optimizationLevel = $quality > 66 ? '-O3' : ($quality > 33 ? '-O2' : '-O1');
                $this->optimizerChain->setOptimizers([new Gifsicle([
                    '-b', $optimizationLevel
                ])]);
            } elseif ($mimeType === 'image/svg+xml') {
                $this->optimizerChain->setOptimizers([new Svgo([
                    '--disable=cleanupIDs'
                ])]);
            } else {
                // For unsupported types, use default chain
                $this->optimizerChain = OptimizerChainFactory::create();
            }
            
            // Apply optimization to the copied file
            $this->optimizerChain->optimize($optimizedPath);
            
            // Get optimized file size
            $optimizedSize = filesize($optimizedPath);
            
            // Check if optimization actually reduced file size
            if ($optimizedSize >= $originalSize) {
                // Optimization made file larger or same size - revert to original
                copy($originalFilePath, $optimizedPath);
                $optimizedSize = $originalSize;
                
                $endTime = microtime(true);
                $processingTime = round(($endTime - $startTime) * 1000, 2);
                
                return [
                    'algorithm' => $this->getAlgorithmForMimeType($mimeType, $quality) . ' (reverted - no size reduction)',
                    'processing_time' => $processingTime . ' ms',
                    'optimized' => true,
                    'original_size' => $originalSize,
                    'optimized_size' => $optimizedSize,
                    'size_reduction' => 0,
                    'compression_ratio' => 0.0,
                    'size_increase_prevented' => true,
                    'reason' => 'Optimization increased file size, reverted to original'
                ];
            }
            
            $endTime = microtime(true);
            $processingTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'algorithm' => $this->getAlgorithmForMimeType($mimeType, $quality),
                'processing_time' => $processingTime . ' ms',
                'optimized' => true,
                'original_size' => $originalSize,
                'optimized_size' => $optimizedSize,
                'size_reduction' => $originalSize - $optimizedSize,
                'compression_ratio' => $this->calculateCompressionRatio($originalSize, $optimizedSize)
            ];
            
        } catch (\Exception $e) {
            // If optimization fails, still create a copy but mark as not optimized
            $optimizedFileName = basename($originalPath);
            $optimizedPath = Storage::disk('public')->path('uploads/optimized/' . $optimizedFileName);
            
            if (!file_exists($optimizedPath)) {
                // Ensure the optimized directory exists
                $optimizedDir = dirname($optimizedPath);
                if (!is_dir($optimizedDir)) {
                    mkdir($optimizedDir, 0755, true);
                }
                
                copy($originalFilePath, $optimizedPath);
            }
            
            $endTime = microtime(true);
            $processingTime = round(($endTime - $startTime) * 1000, 2);
            
            return [
                'algorithm' => 'Optimization failed - file copied without optimization',
                'processing_time' => $processingTime . ' ms',
                'optimized' => false,
                'reason' => 'Optimization error: ' . $e->getMessage(),
                'original_size' => filesize($originalFilePath),
                'optimized_size' => filesize($originalFilePath), // Same as original since not optimized
                'size_reduction' => 0,
                'compression_ratio' => 0.0
            ];
        }
    }

    /**
     * Get optimization algorithm description for MIME type.
     *
     * @param string $mimeType File MIME type
     * @param int $quality The optimization quality (1-100)
     * @return string Algorithm description
     */
    private function getAlgorithmForMimeType(string $mimeType, int $quality): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'JPEG optimization with MozJPEG (quality ' . $quality . ')',
            'image/png' => 'PNG optimization with Pngquant + Optipng (quality ' . $quality . ')',
            'image/webp' => 'WebP optimization with Cwebp (quality ' . $quality . ')',
            'image/gif' => 'GIF optimization with Gifsicle (level ' . $quality . ')',
            'image/svg+xml' => 'SVG optimization with SVGO',
            default => 'Generic image optimization',
        };
    }

    /**
     * Get optimization algorithm description for file extension.
     *
     * @param string $extension File extension
     * @param int $quality The optimization quality (1-100)
     * @return string Algorithm description
     */
    private function getAlgorithmForExtension(string $extension, int $quality = 80): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'JPEG optimization with MozJPEG (quality ' . $quality . ')',
            'png' => 'PNG optimization with Pngquant + Optipng (quality ' . $quality . ')',
            'webp' => 'WebP optimization with Cwebp (quality ' . $quality . ')',
            'avif' => 'AVIF optimization with avifenc (quality ' . $quality . ')',
            'gif' => 'GIF optimization with Gifsicle (level ' . $quality . ')',
            'svg' => 'SVG optimization with SVGO',
            default => 'Generic image optimization',
        };
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

    /**
     * Calculate optimization metrics.
     *
     * @param int $originalSize Original file size in bytes
     * @param int $optimizedSize Optimized file size in bytes
     * @return array Optimization metrics
     */
    public function calculateMetrics(int $originalSize, int $optimizedSize): array
    {
        $compressionRatio = $originalSize > 0 
            ? ($originalSize - $optimizedSize) / $originalSize * 100 
            : 0;

        return [
            'compression_ratio' => round($compressionRatio, 2),
            'size_reduction' => $originalSize - $optimizedSize,
        ];
    }

    /**
     * Get supported file types and their optimization strategies.
     *
     * @return array Supported file types with descriptions
     */
    public function getSupportedTypes(): array
    {
        return [
            'jpg' => 'JPEG optimization with MozJPEG (default quality 80)',
            'jpeg' => 'JPEG optimization with MozJPEG (default quality 80)',
            'png' => 'PNG optimization with Pngquant + Optipng (default quality 80)',
            'webp' => 'WebP optimization with Cwebp (default quality 80)',
            'gif' => 'GIF optimization with Gifsicle (default level 80)',
            'svg' => 'SVG optimization with SVGO',
        ];
    }

    /**
     * Check if file type is supported for optimization.
     *
     * @param string $extension File extension
     * @return bool Whether the file type is supported
     */
    public function isSupported(string $extension): bool
    {
        return array_key_exists(strtolower($extension), $this->getSupportedTypes());
    }

    /**
     * Generate WebP copy of an optimized image.
     *
     * @param string $optimizedPath Path to the optimized image
     * @param string $mimeType MIME type of the source image
     * @param int $quality WebP quality (1-100)
     * @return array WebP generation result data
     */
    public function generateWebpCopy(string $optimizedPath, string $mimeType, int $quality = 80): array
    {
        // Check if WebP conversion is supported for this MIME type
        if (!$this->webpConverter->canConvertToWebp($mimeType)) {
            return [
                'success' => false,
                'reason' => 'File type not supported for WebP conversion',
                'webp_generated' => false
            ];
        }

        // Generate WebP file path
        $webpPath = $this->webpConverter->generateWebpPath($optimizedPath);
        $webpFullPath = Storage::disk('public')->path($webpPath);

        // Convert to WebP
        $result = $this->webpConverter->convertToWebp(
            Storage::disk('public')->path($optimizedPath),
            $webpFullPath,
            $quality
        );

        if ($result['success']) {
            return [
                'success' => true,
                'webp_path' => $webpPath,
                'webp_size' => $result['webp_size'],
                'webp_compression_ratio' => $result['compression_ratio'],
                'webp_size_reduction' => $result['size_reduction'],
                'webp_processing_time' => $result['processing_time'],
                'webp_generated' => true
            ];
        } else {
            return [
                'success' => false,
                'reason' => $result['error'],
                'webp_generated' => false
            ];
        }
    }

    /**
     * Check if file type is supported for WebP conversion.
     *
     * @param string $mimeType File MIME type
     * @return bool Whether the file type supports WebP conversion
     */
    public function supportsWebpConversion(string $mimeType): bool
    {
        return $this->webpConverter->canConvertToWebp($mimeType);
    }

    /**
     * Analyze image to predict optimization potential.
     *
     * @param string $filePath Path to the image file
     * @param int $fileSize File size in bytes
     * @return array Analysis result with optimization recommendations
     */
    public function analyzeOptimizationPotential(string $filePath, int $fileSize): array
    {
        $analysis = [
            'likely_to_benefit' => true,
            'confidence' => 'medium',
            'recommendations' => [],
            'warnings' => []
        ];

        // Very small files (< 5KB) often don't benefit from optimization
        if ($fileSize < 5120) {
            $analysis['likely_to_benefit'] = false;
            $analysis['confidence'] = 'high';
            $analysis['warnings'][] = 'File is very small (' . round($fileSize / 1024, 1) . 'KB) - optimization may increase size';
            return $analysis;
        }

        // Files smaller than 50KB have lower optimization potential
        if ($fileSize < 51200) {
            $analysis['confidence'] = 'low';
            $analysis['warnings'][] = 'Small file size may limit optimization benefits';
        }

        // Try to get image dimensions and quality hints if possible
        try {
            $imageInfo = getimagesize($filePath);
            if ($imageInfo !== false) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
                $pixels = $width * $height;
                $bytesPerPixel = $fileSize / $pixels;

                // Very high bytes per pixel suggests already heavy compression
                if ($bytesPerPixel < 0.5) {
                    $analysis['confidence'] = 'low';
                    $analysis['warnings'][] = 'Image appears heavily compressed already';
                }

                // Very low bytes per pixel suggests good optimization potential
                if ($bytesPerPixel > 3) {
                    $analysis['confidence'] = 'high';
                    $analysis['recommendations'][] = 'Image has high optimization potential';
                }
            }
        } catch (\Exception $e) {
            // Image analysis failed, proceed with default analysis
            $analysis['warnings'][] = 'Could not analyze image properties';
        }

        return $analysis;
    }
} 