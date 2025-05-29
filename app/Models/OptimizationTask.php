<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OptimizationTask extends Model
{
    protected $fillable = [
        'task_id',
        'status',
        'original_filename',
        'original_path',
        'optimized_path',
        'original_size',
        'optimized_size',
        'quality',
        'compression_ratio',
        'size_reduction',
        'algorithm',
        'processing_time',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'compression_ratio' => 'decimal:2',
        'processing_time' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($task) {
            if (empty($task->task_id)) {
                $task->task_id = Str::uuid();
            }
            
            // Set expiration to 24 hours from creation
            if (empty($task->expires_at)) {
                $task->expires_at = now()->addHours(24);
            }
        });
    }

    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(array $optimizationData)
    {
        $this->update([
            'status' => 'completed',
            'optimized_path' => $optimizationData['optimized_path'],
            'optimized_size' => $optimizationData['optimized_size'],
            'compression_ratio' => $optimizationData['compression_ratio'],
            'size_reduction' => $optimizationData['size_reduction'],
            'algorithm' => $optimizationData['algorithm'],
            'processing_time' => $optimizationData['processing_time'],
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Generate the optimized filename based on original filename
     * Example: my_cat.jpeg -> my_cat-optimized.jpeg
     */
    public function getOptimizedFilename(): string
    {
        $pathInfo = pathinfo($this->original_filename);
        $nameWithoutExt = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';
        
        return $nameWithoutExt . '-optimized' . ($extension ? '.' . $extension : '');
    }

    /**
     * Generate the optimized file path for storage
     * Uses UUID for actual storage but maintains filename structure
     */
    public function generateOptimizedPath(): string
    {
        $pathInfo = pathinfo($this->original_path);
        $directory = 'uploads/optimized';
        $filename = basename($this->original_path); // Keep the UUID filename for storage
        
        return $directory . '/' . $filename;
    }
} 