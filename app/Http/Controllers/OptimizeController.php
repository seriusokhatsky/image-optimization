<?php

namespace App\Http\Controllers;

use App\Services\FileOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OptimizeController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private FileOptimizationService $optimizationService
    ) {}

    /**
     * Optimize an uploaded file.
     *
     * @param Request $request The HTTP request containing the file
     * @return JsonResponse The optimization result
     */
    public function optimize(Request $request): JsonResponse
    {
        // Validate the file upload and quality parameter
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'quality' => 'nullable|integer|min:1|max:100', // Quality from 1-100
        ]);

        $file = $request->file('file');
        $quality = $request->input('quality', 80); // Default quality is 80
        $originalName = $file->getClientOriginalName();
        $originalSize = $file->getSize();
        $extension = $file->getClientOriginalExtension();

        // Generate unique filename for storage
        $uniqueName = Str::uuid() . '.' . $extension;
        
        // Store the original file
        $originalPath = $file->storeAs('uploads/original', $uniqueName, 'public');

        // Use optimization service with the original file path and quality
        $optimizationResult = $this->optimizationService->optimize($file, $extension, $originalPath, $quality);

        // Determine optimized file size and metrics
        if ($optimizationResult['optimized']) {
            $optimizedSize = $optimizationResult['optimized_size'];
            $compressionRatio = $optimizationResult['compression_ratio'];
            $sizeReduction = $optimizationResult['size_reduction'];
        } else {
            // If optimization failed, use original size as fallback
            $optimizedSize = $originalSize;
            $compressionRatio = 0;
            $sizeReduction = 0;
        }

        $optimizedPath = 'uploads/optimized/' . $uniqueName;

        // Prepare the response
        $result = [
            'success' => $optimizationResult['optimized'],
            'message' => $optimizationResult['optimized'] 
                ? 'File optimized successfully' 
                : 'File uploaded but optimization failed',
            'data' => [
                'original_file' => [
                    'name' => $originalName,
                    'size' => $originalSize,
                    'path' => $originalPath,
                ],
                'optimized_file' => [
                    'name' => 'optimized_' . $originalName,
                    'size' => $optimizedSize,
                    'path' => $optimizedPath,
                ],
                'optimization' => [
                    'compression_ratio' => $compressionRatio,
                    'size_reduction' => $sizeReduction,
                    'algorithm' => $optimizationResult['algorithm'],
                    'processing_time' => $optimizationResult['processing_time'],
                    'optimized' => $optimizationResult['optimized'],
                ],
                'storage' => [
                    'original_url' => Storage::disk('public')->url($originalPath),
                    'optimized_url' => Storage::disk('public')->url($optimizedPath),
                ],
            ],
        ];

        // Add failure reason if optimization failed
        if (!$optimizationResult['optimized'] && isset($optimizationResult['reason'])) {
            $result['data']['optimization']['failure_reason'] = $optimizationResult['reason'];
        }

        return response()->json($result);
    }
} 