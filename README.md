# Image Optimization API

A Laravel-based REST API for optimizing images with support for multiple formats and asynchronous processing. Built with Docker for easy deployment and includes specialized optimization tools.

## Features

- **Multi-format support**: JPEG, PNG, WebP, GIF, SVG
- **Asynchronous processing**: Background optimization with queue system  
- **WebP generation**: Automatic WebP versions for supported formats
- **Quality control**: Configurable optimization levels
- **Smart optimization**: Prevents file size increases
- **Task tracking**: Real-time status monitoring
- **Auto-cleanup**: Files expire after 24 hours
- **Demo interface**: Web UI for testing

## Quick Start

### Prerequisites
- Docker & Docker Compose
- Git

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd optimizer
   ```

2. **Start with Docker**
   ```bash
   docker compose up -d
   ```

3. **Install dependencies**
   ```bash
   sail composer install
   sail artisan migrate
   ```

4. **Access the application**
   - API: `http://localhost/api/optimize`
   - Demo: `http://localhost`

## API Usage

### Submit Image for Optimization
```bash
POST /api/optimize/submit
Content-Type: multipart/form-data

Parameters:
- file: Image file (max 10MB)
- quality: Optional quality (1-100, default: 80)
```

**Response:**
```json
{
  "success": true,
  "message": "File uploaded successfully. Optimization in progress.",
  "data": {
    "task_id": "uuid-here",
    "status": "pending",
    "original_file": {
      "name": "image.jpg",
      "size": 1024000
    },
    "estimated_completion": "2024-01-01T12:02:00Z"
  }
}
```

### Check Status
```bash
GET /api/optimize/status/{task_id}
```

### Download Optimized Image
```bash
GET /api/optimize/download/{task_id}        # Optimized version
GET /api/optimize/download/{task_id}/webp   # WebP version
```

## WebP Generation Control

By default, the optimizer does NOT generate WebP versions of images to optimize performance. You can enable WebP generation using the `generate_webp` parameter:

### Enable WebP Generation
```bash
curl -X POST http://localhost/api/optimize/submit \
  -F "file=@image.jpg" \
  -F "quality=80" \
  -F "generate_webp=true"
```

### Disable WebP Generation (Default)
```bash
curl -X POST http://localhost/api/optimize/submit \
  -F "file=@image.jpg" \
  -F "quality=80" \
  -F "generate_webp=false"
```

Or simply omit the parameter (defaults to `false`):
```bash
curl -X POST http://localhost/api/optimize/submit \
  -F "file=@image.jpg" \
  -F "quality=80"
```

## Supported Formats

| Format | Optimizer | Features |
|--------|-----------|----------|
| JPEG | MozJPEG | Quality control, progressive encoding |
| PNG | Pngquant + Optipng | Palette reduction, lossless compression |
| WebP | Cwebp | Quality control, automatic conversion |
| GIF | Gifsicle | Optimization levels, animation support |
| SVG | SVGO | Minification, cleanup |

## Development

### Docker Environment

The application runs in a custom Docker container with optimization tools:

- **Base**: Laravel Sail (PHP 8.4)
- **Optimizers**: jpegoptim, optipng, pngquant, gifsicle
- **WebP tools**: cwebp, dwebp
- **Custom**: MozJPEG (compiled from source)

#### Docker Customizations

We run the app in Docker using Laravel Sail. All commands should use sail. Example:
`sail artisan migrate` instead of `php artisan migrate`

The original Docker Sail image is customized by adding these commands to `docker/8.4/Dockerfile`:

```dockerfile
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    # Image optimization binaries
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    # WebP tools
    webp \
    # AVIF tools
    libavif-dev \
    libavif-bin \
    # Build dependencies for MozJPEG
    automake \
    autoconf \
    libtool \
    cmake \
    nasm \
    build-essential

# Install MozJPEG
RUN cd /tmp && \
    git clone https://github.com/mozilla/mozjpeg.git && \
    cd mozjpeg && \
    mkdir build && \
    cd build && \
    cmake -DCMAKE_INSTALL_PREFIX=/usr/local .. && \
    make && \
    make install && \
    ldconfig && \
    rm -rf /tmp/mozjpeg
```

### Common Commands

```bash
# Start services
docker compose up -d

# Run artisan commands
sail artisan migrate
sail artisan queue:work

# Install packages
sail composer install
sail npm install

# View logs
sail artisan log:tail
docker compose logs -f
```

### Queue Processing

The API uses Laravel queues for background processing:

```bash
# Start queue worker
sail artisan queue:work

# Monitor queue
sail artisan queue:monitor
```

### File Structure

```
app/
├── Http/Controllers/
│   ├── OptimizeController.php    # Main API controller
│   └── HomeController.php        # Demo interface
├── Models/
│   └── OptimizationTask.php      # Task tracking model
├── Services/
│   ├── FileOptimizationService.php  # Core optimization logic
│   ├── WebpConverterService.php     # WebP generation
│   └── Optimizers/
│       └── MozjpegOptimizer.php     # Custom JPEG optimizer
└── Jobs/
    └── OptimizeFileJob.php        # Background processing
```

## Configuration

### Environment Variables

```env
# Queue driver (required for async processing)
QUEUE_CONNECTION=database

# File storage
FILESYSTEM_DISK=public

# App settings
APP_URL=http://localhost
```

### Optimization Settings

Default settings can be modified in `app/Services/FileOptimizationService.php`:

- **Quality levels**: Per-format quality settings
- **Timeout**: Processing timeout (default: 5 minutes)
- **Retries**: Job retry attempts (default: 3)
- **Expiration**: Task cleanup time (default: 24 hours)

## Production Deployment

### Requirements
- Docker & Docker Compose
- Supervisor (for queue workers)
- SSL certificates (recommended)

### Steps
1. Set `APP_ENV=production`
2. Configure production database
3. Set up Supervisor for queue workers
4. Configure reverse proxy (nginx)
5. Set up SSL certificates

## Monitoring

### Health Check
```bash
GET /health
```

### Queue Monitoring
```bash
# View queue status
sail artisan queue:monitor

# Clear failed jobs
sail artisan queue:flush
```

### Logs
- Application: `storage/logs/laravel.log`
- Queue processing: Dedicated optimization logs
- Docker: `docker compose logs`

## Troubleshooting

### Common Issues

**Queue not processing:**
```bash
sail artisan queue:work --verbose
```

**Optimization fails:**
- Check if optimization binaries are installed
- Verify file permissions
- Check available disk space

**Large file uploads:**
- Adjust `upload_max_filesize` in PHP
- Increase `post_max_size`
- Configure web server limits

## License

[Your License Here]