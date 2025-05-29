<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OptimizeController extends Controller
{
    /**
     * Optimize an uploaded file.
     */
    public function optimize(Request $request): JsonResponse
    {
        // Validate the file upload
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $originalSize = $file->getSize();
        $extension = $file->getClientOriginalExtension();

        // Generate unique filename for storage
        $uniqueName = Str::uuid() . '.' . $extension;
        
        // Store the original file
        $originalPath = $file->storeAs('uploads/original', $uniqueName, 'public');

        // Placeholder optimization function
        $optimizationResult = $this->performOptimization($file, $extension);

        // Store the optimized file (for now, just copy the original)
        $optimizedPath = 'uploads/optimized/' . $uniqueName;
        Storage::disk('public')->copy('uploads/original/' . $uniqueName, $optimizedPath);

        // Calculate optimization metrics (placeholder values)
        $optimizedSize = $originalSize * 0.75; // Simulate 25% reduction
        $compressionRatio = ($originalSize - $optimizedSize) / $originalSize * 100;

        // Prepare the response
        $result = [
            'success' => true,
            'message' => 'File optimized successfully',
            'data' => [
                'original_file' => [
                    'name' => $originalName,
                    'size' => $originalSize,
                    'path' => $originalPath,
                ],
                'optimized_file' => [
                    'name' => 'optimized_' . $originalName,
                    'size' => (int) $optimizedSize,
                    'path' => $optimizedPath,
                ],
                'optimization' => [
                    'compression_ratio' => round($compressionRatio, 2),
                    'size_reduction' => $originalSize - (int) $optimizedSize,
                    'algorithm' => $optimizationResult['algorithm'],
                    'processing_time' => $optimizationResult['processing_time'],
                ],
                'storage' => [
                    'original_url' => Storage::disk('public')->url($originalPath),
                    'optimized_url' => Storage::disk('public')->url($optimizedPath),
                ],
            ],
        ];

        return response()->json($result);
    }

    /**
     * Placeholder optimization function.
     * In a real implementation, this would contain actual optimization logic.
     */
    private function performOptimization($file, string $extension): array
    {
        $startTime = microtime(true);

        // Placeholder optimization logic based on file type
        $algorithm = match (strtolower($extension)) {
            'jpg', 'jpeg' => 'JPEG compression with quality reduction',
            'png' => 'PNG compression with palette optimization',
            'webp' => 'WebP advanced compression',
            'avif' => 'AVIF next-gen compression',
            'gif' => 'GIF color palette optimization',
            default => 'Generic file compression',
        };

        // Simulate processing time
        usleep(rand(100000, 500000)); // 0.1 to 0.5 seconds

        $endTime = microtime(true);
        $processingTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

        return [
            'algorithm' => $algorithm,
            'processing_time' => $processingTime . ' ms',
        ];
    }
} 