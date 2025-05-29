<?php

namespace App\Services\Optimizers;

use Spatie\ImageOptimizer\Image;
use Spatie\ImageOptimizer\Optimizers\BaseOptimizer;

class MozjpegOptimizer extends BaseOptimizer
{
    public $binaryName = 'cjpeg';

    public function canHandle(Image $image): bool
    {
        return $image->mime() === 'image/jpeg';
    }

    public function getCommand(): string
    {
        $optionString = implode(' ', $this->options);
        
        // Directly overwriting of file is not possible with cjpeg, so do a mv after compression
        return "{$this->binaryName} {$optionString}"
            . ' -outfile ' . escapeshellarg($this->imagePath . '.optimized.jpg')
            . ' ' . escapeshellarg($this->imagePath)
            . ' && mv ' . escapeshellarg($this->imagePath . '.optimized.jpg')
            . ' ' . escapeshellarg($this->imagePath);
    }
} 