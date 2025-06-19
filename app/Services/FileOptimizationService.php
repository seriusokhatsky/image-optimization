<?php

namespace App\Services;

use App\Services\Optimizers\MozjpegOptimizer;
use App\Services\WebpConverterService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Pngquant;

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
     * @param int $quality The optimization quality (1-100)
     * @return array Optimization result data
     */
    public function optimize(UploadedFile $file, int $quality = 80): array
    {
        $startTime = microtime(true);
        
        // Get the full system path to the original file
        $originalFilePath = $file->getRealPath();
        
        // Check if file type is supported for optimization
        $mimeType = $file->getMimeType();
        if (!$this->isSupported($mimeType)) {
            return [
                'algorithm' => 'Generic file compression (not optimized)',
                'processing_time' => '0 ms',
                'optimized' => false,
                'reason' => 'File type not supported for image optimization'
            ];
        }

        try {
            $originalSize = $file->getSize();
            $optimizedFileName = $file->getClientOriginalName();
            $storedPath = $file->storeAs('uploads/optimized', $optimizedFileName, 'public');
            $optimizedPath = Storage::disk('public')->path($storedPath);
            
            // Use specific optimizers based on file type
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
            }
            
            // Apply optimization to the copied file
            $this->optimizerChain->optimize($optimizedPath);
            
            // Get optimized file size
            $optimizedSize = Storage::disk('public')->size($storedPath);
            
            // Check if optimization actually reduced file size
            if ($optimizedSize >= $originalSize) {
                // Optimization made file larger or same size - revert to original
                $file->storeAs('uploads/optimized', $optimizedFileName, 'public');
                $optimizedSize = $originalSize;
                
                return [
                    'algorithm' => $this->getAlgorithmForMimeType($mimeType, $quality) . ' (reverted - no size reduction)',
                    'processing_time' => $this->calculateProcessingTime($startTime),
                    'optimized' => true,
                    'original_size' => $originalSize,
                    'optimized_size' => $optimizedSize,
                    'size_reduction' => 0,
                    'compression_ratio' => 0.0,
                    'size_increase_prevented' => true,
                    'reason' => 'Optimization increased file size, reverted to original'
                ];
            }

            return [
                'algorithm' => $this->getAlgorithmForMimeType($mimeType, $quality),
                'processing_time' => $this->calculateProcessingTime($startTime),
                'optimized' => true,
                'original_size' => $originalSize,
                'optimized_size' => $optimizedSize,
                'size_reduction' => $originalSize - $optimizedSize,
                'compression_ratio' => $this->calculateCompressionRatio($originalSize, $optimizedSize)
            ];
            
        } catch (\Exception $e) {
            // If optimization fails, still create a copy but mark as not optimized
            $optimizedFileName = $file->getClientOriginalName();
            $storedPath = $file->storeAs('uploads/optimized', $optimizedFileName, 'public');
            $optimizedPath = Storage::disk('public')->path($storedPath);
            
            return [
                'algorithm' => 'Optimization failed - file copied without optimization',
                'processing_time' => $this->calculateProcessingTime($startTime),
                'optimized' => false,
                'reason' => 'Optimization error: ' . $e->getMessage(),
                'original_size' => $originalSize,
                'optimized_size' => $originalSize, // Same as original since not optimized
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
            default => 'Generic image optimization',
        };
    }

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


    /**
     * Check if file type is supported for optimization.
     *
     * @param string $mimeType File MIME type
     * @return bool Whether the file type is supported
     */
    public function isSupported(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ]);
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

} 