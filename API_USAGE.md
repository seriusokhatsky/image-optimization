# File Optimization API

This Laravel application provides a file optimization API endpoint with a service-based architecture and file-type-specific optimizers.

## Architecture

The optimization functionality is implemented using:
- **OptimizeController**: Handles HTTP requests and responses
- **FileOptimizationService**: Contains the core optimization logic with file-type-specific optimizer selection
- **Custom Optimizers**: Including MozjpegOptimizer for enhanced JPEG optimization
- **File-Type-Specific Optimization**: Uses `setOptimizers()` to apply the best optimizer for each file type
- **Dependency Injection**: The service is injected into the controller for better testability

## File-Type-Specific Optimization

The service automatically selects the best optimizer based on the uploaded file's MIME type:

### JPEG Files (`image/jpeg`)
```php
$optimizerChain->setOptimizers([new MozjpegOptimizer([
    '-quality', '80'
])]);
```
- **Optimizer**: MozJPEG (cjpeg binary)
- **Quality**: 80% for optimal size/quality balance
- **Result**: Superior JPEG compression with excellent quality preservation

### WebP Files (`image/webp`)
```php
$optimizerChain->setOptimizers([new Cwebp([
    '-q', '80'
])]);
```
- **Optimizer**: Cwebp
- **Quality**: 80% compression
- **Result**: Optimized WebP files with modern compression

### PNG Files (`image/png`)
```php
$optimizerChain->setOptimizers([
    new Pngquant(['--force']),
    new Optipng(['-i0', '-o2', '-quiet'])
]);
```
- **Optimizers**: Pngquant (lossy) + Optipng (lossless)
- **Result**: Two-stage PNG optimization for maximum compression

### GIF Files (`image/gif`)
```php
$optimizerChain->setOptimizers([new Gifsicle([
    '-b', '-O3'
])]);
```
- **Optimizer**: Gifsicle with maximum optimization level
- **Result**: Optimized GIF animations and static images

### SVG Files (`image/svg+xml`)
```php
$optimizerChain->setOptimizers([new Svgo([
    '--disable=cleanupIDs'
])]);
```
- **Optimizer**: SVGO with safe settings
- **Result**: Minified SVG files with preserved functionality

## Custom Optimizers

### MozjpegOptimizer

The application includes a custom MozJPEG optimizer (`App\Services\Optimizers\MozjpegOptimizer`) that provides superior JPEG compression:

- **Binary**: Uses `cjpeg` (MozJPEG encoder)
- **Quality**: Set to 80% for optimal size/quality balance
- **Features**: Advanced JPEG optimization
- **Compatibility**: Handles JPEG/JPG images only

To install MozJPEG on macOS:
```bash
brew install mozjpeg
```

## Endpoint

### POST `/api/optimize`

Accepts a file upload, automatically selects the optimal optimization strategy based on file type, stores both original and optimized versions, and returns JSON with optimization results.

#### Request

- **Method:** POST
- **URL:** `/api/optimize`
- **Content-Type:** `multipart/form-data`
- **Parameters:**
  - `file` (required): The file to optimize (max 10MB)

#### Example cURL Request

```bash
curl -X POST \
  http://your-app-url/api/optimize \
  -F "file=@/path/to/your/image.jpg"
```

#### Response

```json
{
  "success": true,
  "message": "File optimized successfully",
  "data": {
    "original_file": {
      "name": "image.jpg",
      "size": 1024000,
      "path": "uploads/original/uuid.jpg"
    },
    "optimized_file": {
      "name": "optimized_image.jpg",
      "size": 768000,
      "path": "uploads/optimized/uuid.jpg"
    },
    "optimization": {
      "compression_ratio": 25.0,
      "size_reduction": 256000,
      "algorithm": "JPEG optimization with MozJPEG (quality 80)",
      "processing_time": "234.56 ms",
      "optimized": true
    },
    "storage": {
      "original_url": "http://your-app-url/storage/uploads/original/uuid.jpg",
      "optimized_url": "http://your-app-url/storage/uploads/optimized/uuid.jpg"
    }
  }
}
```

#### Supported File Types

The API automatically selects optimizers based on file type:

- **JPEG/JPG**: MozJPEG with quality 80 for superior compression
- **PNG**: Pngquant + Optipng for dual-stage optimization
- **WebP**: Cwebp with quality 80 for modern compression
- **GIF**: Gifsicle with maximum optimization
- **SVG**: SVGO with safe minification settings

#### Error Response

```json
{
  "message": "The file field is required.",
  "errors": {
    "file": [
      "The file field is required."
    ]
  }
}
```

#### Optimization Failure Response

```json
{
  "success": false,
  "message": "File uploaded but optimization failed",
  "data": {
    "optimization": {
      "optimized": false,
      "failure_reason": "Optimization error: Binary not found",
      "algorithm": "Optimization failed - file copied without optimization"
    }
  }
}
```

## Development Notes

- **File-type-specific optimization**: Uses `setOptimizers()` to apply the best optimizer for each MIME type
- **Custom optimizers**: MozJPEG optimizer provides enhanced JPEG compression
- **Service-based architecture**: Optimization logic is separated into `FileOptimizationService`
- **Dependency injection**: The service is injected into the controller constructor
- **Graceful degradation**: If optimization tools are missing, files are copied without optimization
- **MIME type detection**: Uses `$file->getMimeType()` for accurate file type detection
- Files are stored in `storage/app/public/uploads/`
- Original files are in the `original` subdirectory
- Optimized files are in the `optimized` subdirectory

## Service Methods

The `FileOptimizationService` provides the following methods:

- `optimize(UploadedFile $file, string $extension, string $originalPath)`: Performs file-type-specific optimization
- `calculateMetrics(int $originalSize, int $optimizedSize)`: Calculates compression metrics
- `getSupportedTypes()`: Returns array of supported file types
- `isSupported(string $extension)`: Checks if file type is supported

## Custom Optimizer Development

To create additional custom optimizers:

1. Create a class extending `Spatie\ImageOptimizer\Optimizers\BaseOptimizer`
2. Implement required methods: `canHandle()` and `getCommand()`
3. Set the `$binaryName` property
4. Add to the file-type-specific logic in `FileOptimizationService::optimize()`

Example:
```php
<?php

namespace App\Services\Optimizers;

use Spatie\ImageOptimizer\Image;
use Spatie\ImageOptimizer\Optimizers\BaseOptimizer;

class CustomOptimizer extends BaseOptimizer
{
    public $binaryName = 'your-binary';

    public function canHandle(Image $image): bool
    {
        return $image->mime() === 'image/jpeg';
    }

    public function getCommand(): string
    {
        $optionString = implode(' ', $this->options);
        return "{$this->binaryName} {$optionString}"
            . ' -outfile ' . escapeshellarg($this->imagePath . '.optimized.jpg')
            . ' ' . escapeshellarg($this->imagePath)
            . ' && mv ' . escapeshellarg($this->imagePath . '.optimized.jpg')
            . ' ' . escapeshellarg($this->imagePath);
    }
}
```

Then add it to the service:
```php
if ($mimeType === 'image/jpeg') {
    $this->optimizerChain->setOptimizers([new CustomOptimizer([
        '-option', 'value'
    ])]);
}
```

## Testing

Test the API endpoint directly:

```bash
# Start Laravel server
php artisan serve

# Test with a JPEG file
curl -X POST http://localhost:8000/api/optimize -F "file=@image.jpg"

# Test with different file types
curl -X POST http://localhost:8000/api/optimize -F "file=@image.png"
curl -X POST http://localhost:8000/api/optimize -F "file=@image.webp"
```

## Installation Requirements

### Required Binaries

For full optimization support, install these binaries:

**macOS (Homebrew):**
```bash
brew install jpegoptim optipng pngquant gifsicle webp libavif mozjpeg
npm install -g svgo
```

**Ubuntu/Debian:**
```bash
sudo apt-get install jpegoptim optipng pngquant gifsicle webp libavif-bin
sudo npm install -g svgo
```

**For MozJPEG specifically:**
```bash
# macOS
brew install mozjpeg

# Ubuntu/Debian (compile from source)
wget https://github.com/mozilla/mozjpeg/releases/download/v4.1.1/mozjpeg-4.1.1.tar.gz
tar -xzf mozjpeg-4.1.1.tar.gz
cd mozjpeg-4.1.1
mkdir build && cd build
cmake -G"Unix Makefiles" ../
make install
```

## Next Steps

To enhance the optimization further:

1. Install additional optimization binaries (MozJPEG, etc.)
2. Add more file type specific optimizers (HEIC, JXL, etc.)
3. Implement quality presets (web, print, thumbnail)
4. Add batch processing capabilities
5. Create optimization queues for large files
6. Add progressive JPEG support for large images
7. Implement format conversion (e.g., PNG to WebP)
8. Add optimization profiles for different use cases 