# Docker Build Time Optimization

This document explains the multi-stage Docker build optimization implemented for the Laravel image optimization tool.

## Overview

The production Dockerfile has been optimized using multi-stage builds to dramatically reduce build times by leveraging Docker's layer caching mechanisms. Instead of installing all dependencies every time, only the actual application code needs to be rebuilt when you pull changes from GitHub.

## Build Stages

### Stage 1: Base Dependencies (`base-dependencies`)
- **Purpose**: Install all system dependencies, PHP, Node.js, and image optimization tools
- **Cache Duration**: This layer is cached indefinitely unless the base Ubuntu image or package versions change
- **Contents**: 
  - Ubuntu 24.04 base image
  - PHP 8.4 with all required extensions
  - Node.js 22
  - Image optimization tools (MozJPEG, WebP, AVIF, etc.)
  - System packages and libraries

### Stage 2: PHP Dependencies (`php-dependencies`)
- **Purpose**: Install Composer dependencies
- **Cache Duration**: Cached until `composer.json` or `composer.lock` changes
- **Optimization**: Only copies dependency files, not the entire codebase

### Stage 3: Node Dependencies (`node-dependencies`)
- **Purpose**: Install Node.js dependencies
- **Cache Duration**: Cached until `package.json` or `package-lock.json` changes
- **Optimization**: Production-only dependencies installed

### Stage 4: Asset Builder (`asset-builder`)
- **Purpose**: Build frontend assets (CSS, JS)
- **Cache Duration**: Cached until frontend source files change
- **Optimization**: Installs dev dependencies only for building, then discards them

### Stage 5: Final Production (`production`)
- **Purpose**: Combine all dependencies with application code
- **Contents**: 
  - Copies vendor dependencies from Stage 2
  - Copies built assets from Stage 4
  - Copies application code (the only layer that changes frequently)

## Performance Benefits

### Before Optimization (Single-stage build)
- **First Build**: ~15-20 minutes
- **Subsequent Builds**: ~15-20 minutes (everything rebuilt from scratch)
- **Code Change**: ~15-20 minutes (full rebuild)

### After Optimization (Multi-stage build)
- **First Build**: ~15-20 minutes (same as before)
- **Subsequent Builds with Code Changes**: ~2-5 minutes (only rebuilds final stage)
- **Dependency Changes**: 
  - PHP dependencies only: ~5-8 minutes
  - Frontend dependencies only: ~3-6 minutes
  - System dependencies: Full rebuild (rare)

## Usage

### Basic Local Build
```bash
# Using the optimized build script
./docker/production/build-optimized.sh

# Or using docker compose (will use multi-stage automatically)
docker compose -f docker-compose.prod.yml build
```

### Advanced Build with Registry Cache
```bash
# Build with registry cache (for CI/CD)
./docker/production/build-optimized.sh \
  --cache-from-registry \
  --registry-url your-registry.com

# Build and push cache layers
./docker/production/build-optimized.sh \
  --cache-from-registry \
  --registry-url your-registry.com \
  --push-cache
```

### Build Script Options
- `--cache-from-registry`: Use registry cache instead of local cache
- `--registry-url URL`: Registry URL for cache storage
- `--push-cache`: Push cache layers to registry after build
- `--build-arg KEY=VALUE`: Pass build arguments to Docker

## CI/CD Integration

### GitHub Actions Example
```yaml
- name: Set up Docker Buildx
  uses: docker/setup-buildx-action@v2

- name: Build with cache
  run: |
    ./docker/production/build-optimized.sh \
      --cache-from-registry \
      --registry-url ${{ vars.REGISTRY_URL }} \
      --push-cache
```

### GitLab CI Example
```yaml
build:
  script:
    - docker buildx create --use
    - ./docker/production/build-optimized.sh \
        --cache-from-registry \
        --registry-url $CI_REGISTRY \
        --push-cache
```

## Cache Management

### Local Cache
- **Location**: `/tmp/.buildx-cache`
- **Persistence**: Survives system restarts
- **Management**: Automatically managed by the build script

### Registry Cache
- **Benefits**: Shared across team members and CI/CD
- **Storage**: Each build stage cached as separate image
- **Cleanup**: Implement registry cleanup policies for old cache images

## File Structure Optimization

The `.dockerignore` file has been optimized to:
- Exclude unnecessary files from build context
- Keep required dependency files for caching
- Reduce context transfer time

## Best Practices

### For Developers
1. Use the optimized build script instead of direct `docker build`
2. Don't change `composer.json`/`composer.lock` unnecessarily
3. Group frontend changes to minimize asset rebuilds

### For CI/CD
1. Use registry cache for consistent performance
2. Push cache layers after successful builds
3. Use cache for pull request builds

### For Production Deployments
1. Pre-build and cache base images
2. Use registry cache to avoid rebuilding on each deployment
3. Monitor cache hit rates

## Troubleshooting

### Cache Issues
```bash
# Clear local cache
rm -rf /tmp/.buildx-cache

# Rebuild without cache
docker buildx build --no-cache -f docker/production/Dockerfile .
```

### Registry Cache Issues
```bash
# Check cache images
docker buildx imagetools inspect your-registry.com/optimizer-app:cache-base-dependencies
```

### Performance Monitoring
```bash
# Check build time
time ./docker/production/build-optimized.sh

# Monitor cache usage
docker system df
```

## Expected Performance Improvements

| Scenario | Before | After | Improvement |
|----------|--------|-------|-------------|
| Code changes only | 15-20 min | 2-5 min | 70-80% faster |
| Composer changes | 15-20 min | 5-8 min | 60-70% faster |
| Frontend changes | 15-20 min | 3-6 min | 70-80% faster |
| First build | 15-20 min | 15-20 min | Same |
| CI/CD builds | 15-20 min | 3-8 min | 60-80% faster |

The optimization is particularly effective for:
- Frequent code deployments
- CI/CD pipelines
- Development environments
- Team collaboration (shared registry cache) 