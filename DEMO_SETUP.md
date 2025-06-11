# Image Optimizer Demo Interface

A simple web interface for the image optimization API, similar to imagecompressor.com.

## Features

- **Drag & Drop Upload**: Easy file selection with drag and drop support
- **Quality Control**: Adjustable quality slider (1-100%)
- **Rate Limiting**: Maximum 10 uploads per IP address per hour
- **Real-time Progress**: Live status updates during optimization
- **Multiple Formats**: Support for JPEG, PNG, GIF, and WebP
- **WebP Generation**: Automatic WebP variants when beneficial
- **Download Links**: Direct download of optimized images
- **File Cleanup**: Automatic cleanup after 1 hour

## Supported File Types

- **JPEG** (.jpg, .jpeg) - MozJPEG optimization
- **PNG** (.png) - Pngquant + Optipng optimization
- **GIF** (.gif) - Gifsicle optimization
- **WebP** (.webp) - Cwebp optimization

## Setup Instructions

### 1. Start the Application

```bash
# Start all services including the queue worker
docker compose up -d

# Or if you prefer to see logs
docker compose up
```

### 2. Run Database Migrations

```bash
# Create necessary database tables
docker compose exec laravel.test php artisan migrate
```

### 3. Access the Demo

Open your browser and navigate to:
```
http://localhost:8080
```

## Rate Limiting

- **10 uploads per hour** per IP address
- Counter resets after 1 hour
- Clear error messages when limit is reached
- Applies to both web interface and API

## Queue System

The demo uses Laravel's queue system for background processing:

- **Queue Worker**: Dedicated container processes optimization jobs
- **Database Queue**: Jobs stored in database for reliability
- **Retry Logic**: Failed jobs are retried up to 3 times
- **Timeout**: 5-minute timeout per optimization job

## File Storage

- **Original Files**: Stored in `storage/app/public/uploads/original/`
- **Optimized Files**: Stored in `storage/app/public/uploads/optimized/`
- **WebP Files**: Stored alongside optimized files with `.webp` extension
- **Auto Cleanup**: Files deleted after 1 hour

## API Endpoints

The demo interface uses these endpoints:

- `POST /demo/upload` - Upload file for optimization
- `GET /demo/status/{taskId}` - Check optimization status
- `GET /download/{taskId}` - Download optimized file
- `GET /download/{taskId}/webp` - Download WebP version

## Troubleshooting

### Queue Not Processing

If uploads seem stuck in "processing" status:

```bash
# Check queue worker status
docker compose logs queue

# Restart queue worker
docker compose restart queue
```

### File Upload Issues

- Check file size (max 10MB)
- Verify file type is supported
- Ensure rate limit not exceeded

### Storage Permissions

```bash
# Fix storage permissions if needed
docker compose exec laravel.test chmod -R 775 storage/app/public
```

## Customization

### Adjust Rate Limits

Edit `app/Http/Middleware/ImageUploadRateLimit.php`:

```php
// Change from 10 to your desired limit
if (RateLimiter::tooManyAttempts($key, 10)) {
```

### Modify Quality Defaults

Edit `resources/views/demo.blade.php`:

```javascript
// Change default quality from 80
<input type="range" id="qualitySlider" min="1" max="100" value="80">
```

### File Size Limits

Edit `app/Http/Controllers/HomeController.php`:

```php
// Change max file size (in KB)
'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:10240'
```

## Security Features

- CSRF protection on all forms
- File type validation
- File size limits
- Rate limiting per IP
- Automatic file cleanup
- Input sanitization

## Performance

- Async processing prevents timeouts
- Queue system handles multiple uploads
- Optimized for large files (up to 10MB)
- Background WebP generation
- Efficient file storage management 