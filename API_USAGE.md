# File Optimization API

This Laravel application provides a file optimization API endpoint.

## Endpoint

### POST `/api/optimize`

Accepts a file upload, optimizes it (placeholder implementation), stores both original and optimized versions, and returns JSON with optimization results.

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
      "algorithm": "JPEG compression with quality reduction",
      "processing_time": "234.56 ms"
    },
    "storage": {
      "original_url": "http://your-app-url/storage/uploads/original/uuid.jpg",
      "optimized_url": "http://your-app-url/storage/uploads/optimized/uuid.jpg"
    }
  }
}
```

#### Supported File Types

The API accepts various file types and applies different optimization algorithms:

- **JPEG/JPG**: JPEG compression with quality reduction
- **PNG**: PNG compression with palette optimization  
- **WebP**: WebP advanced compression
- **AVIF**: AVIF next-gen compression
- **GIF**: GIF color palette optimization
- **Other**: Generic file compression

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

## Development Notes

- The current implementation is a placeholder that simulates optimization
- Files are stored in `storage/app/public/uploads/`
- Original files are in the `original` subdirectory
- Optimized files are in the `optimized` subdirectory
- The optimization currently just copies the original file and simulates compression metrics
- Processing time is artificially simulated between 0.1-0.5 seconds

## Next Steps

To implement real optimization, you would:

1. Install image optimization libraries (e.g., Intervention Image, ImageMagick)
2. Replace the placeholder `performOptimization()` method with actual optimization logic
3. Add more sophisticated file type detection and validation
4. Implement different optimization strategies based on file types
5. Add configuration options for optimization levels
6. Add batch processing capabilities 