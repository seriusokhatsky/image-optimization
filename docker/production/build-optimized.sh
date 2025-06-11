#!/bin/bash

# Production Build Script with Advanced Caching
# This script uses Docker BuildKit for optimal caching and build performance

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
IMAGE_NAME="optimizer-app"
TAG="production"
CACHE_FROM_REGISTRY=${CACHE_FROM_REGISTRY:-"false"}
REGISTRY_URL=${REGISTRY_URL:-""}
BUILD_ARGS=""

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if Docker BuildKit is enabled
check_buildkit() {
    if [ "$DOCKER_BUILDKIT" != "1" ]; then
        print_warning "Docker BuildKit is not enabled. Enabling for this build..."
        export DOCKER_BUILDKIT=1
    fi
    
    if ! docker buildx version >/dev/null 2>&1; then
        print_error "Docker BuildKit/buildx is not available. Please install Docker Desktop or enable BuildKit."
        exit 1
    fi
}

# Function to create buildx builder if needed
setup_builder() {
    if ! docker buildx inspect optimizer-builder >/dev/null 2>&1; then
        print_status "Creating BuildKit builder instance..."
        docker buildx create --name optimizer-builder --driver docker-container --bootstrap
    fi
    
    print_status "Using BuildKit builder: optimizer-builder"
    docker buildx use optimizer-builder
}

# Function to build with cache optimization
build_with_cache() {
    local cache_args=""
    
    # If registry is provided, use registry cache
    if [ "$CACHE_FROM_REGISTRY" = "true" ] && [ -n "$REGISTRY_URL" ]; then
        print_status "Using registry cache from $REGISTRY_URL"
        cache_args="--cache-from=type=registry,ref=$REGISTRY_URL/$IMAGE_NAME:cache-base-dependencies \
                   --cache-from=type=registry,ref=$REGISTRY_URL/$IMAGE_NAME:cache-php-dependencies \
                   --cache-from=type=registry,ref=$REGISTRY_URL/$IMAGE_NAME:cache-node-dependencies \
                   --cache-from=type=registry,ref=$REGISTRY_URL/$IMAGE_NAME:cache-asset-builder \
                   --cache-to=type=registry,ref=$REGISTRY_URL/$IMAGE_NAME:cache-base-dependencies,mode=max \
                   --cache-to=type=registry,ref=$REGISTRY_URL/$IMAGE_NAME:cache-php-dependencies,mode=max \
                   --cache-to=type=registry,ref=$REGISTRY_URL/$IMAGE_NAME:cache-node-dependencies,mode=max \
                   --cache-to=type=registry,ref=$REGISTRY_URL/$IMAGE_NAME:cache-asset-builder,mode=max"
    else
        # Use local cache
        print_status "Using local BuildKit cache"
        cache_args="--cache-from=type=local,src=/tmp/.buildx-cache \
                   --cache-to=type=local,dest=/tmp/.buildx-cache-new,mode=max"
    fi
    
    print_status "Building optimized production image..."
    print_status "This may take a while on first build, but subsequent builds will be much faster!"
    
    # Build the image with cache optimization
    docker buildx build \
        --platform linux/amd64 \
        --target production \
        $cache_args \
        --load \
        -t "$IMAGE_NAME:$TAG" \
        $BUILD_ARGS \
        -f docker/production/Dockerfile \
        .
    
    # If using local cache, replace old cache with new
    if [ "$CACHE_FROM_REGISTRY" != "true" ]; then
        if [ -d "/tmp/.buildx-cache-new" ]; then
            rm -rf /tmp/.buildx-cache
            mv /tmp/.buildx-cache-new /tmp/.buildx-cache
        fi
    fi
}

# Function to show cache statistics
show_cache_info() {
    print_status "Cache information:"
    
    if [ "$CACHE_FROM_REGISTRY" = "true" ] && [ -n "$REGISTRY_URL" ]; then
        echo "  - Cache Type: Registry"
        echo "  - Registry: $REGISTRY_URL"
    else
        echo "  - Cache Type: Local"
        if [ -d "/tmp/.buildx-cache" ]; then
            cache_size=$(du -sh /tmp/.buildx-cache 2>/dev/null | cut -f1 || echo "Unknown")
            echo "  - Cache Size: $cache_size"
        else
            echo "  - Cache Size: No cache found (first build)"
        fi
    fi
}

# Function to push cache to registry (if configured)
push_cache_to_registry() {
    if [ "$CACHE_FROM_REGISTRY" = "true" ] && [ -n "$REGISTRY_URL" ]; then
        print_status "Pushing cache layers to registry..."
        
        # Push each stage as a cache image
        for stage in base-dependencies php-dependencies node-dependencies asset-builder; do
            print_status "Building and pushing cache for stage: $stage"
            docker buildx build \
                --platform linux/amd64 \
                --target "$stage" \
                --cache-from=type=registry,ref=$REGISTRY_URL/$IMAGE_NAME:cache-$stage \
                --cache-to=type=registry,ref=$REGISTRY_URL/$IMAGE_NAME:cache-$stage,mode=max \
                --push \
                -t "$REGISTRY_URL/$IMAGE_NAME:cache-$stage" \
                -f docker/production/Dockerfile \
                .
        done
    fi
}

# Main execution
main() {
    print_status "Starting optimized production build..."
    print_status "Image: $IMAGE_NAME:$TAG"
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --cache-from-registry)
                CACHE_FROM_REGISTRY="true"
                shift
                ;;
            --registry-url)
                REGISTRY_URL="$2"
                shift 2
                ;;
            --push-cache)
                PUSH_CACHE="true"
                shift
                ;;
            --build-arg)
                BUILD_ARGS="$BUILD_ARGS --build-arg $2"
                shift 2
                ;;
            --help|-h)
                cat << EOF
Usage: $0 [OPTIONS]

Options:
    --cache-from-registry      Use registry cache instead of local cache
    --registry-url URL         Registry URL for cache (required with --cache-from-registry)
    --push-cache              Push cache layers to registry after build
    --build-arg KEY=VALUE     Pass build arguments to Docker
    --help, -h                Show this help message

Examples:
    # Local build with local cache
    $0
    
    # Build with registry cache
    $0 --cache-from-registry --registry-url your-registry.com
    
    # Build and push cache to registry
    $0 --cache-from-registry --registry-url your-registry.com --push-cache

EOF
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                exit 1
                ;;
        esac
    done
    
    # Validate registry URL if using registry cache
    if [ "$CACHE_FROM_REGISTRY" = "true" ] && [ -z "$REGISTRY_URL" ]; then
        print_error "Registry URL is required when using registry cache"
        exit 1
    fi
    
    # Check prerequisites
    check_buildkit
    setup_builder
    show_cache_info
    
    # Build the image
    build_with_cache
    
    # Push cache if requested
    if [ "$PUSH_CACHE" = "true" ]; then
        push_cache_to_registry
    fi
    
    print_success "Build completed successfully!"
    print_success "Image: $IMAGE_NAME:$TAG"
    
    # Show image size
    image_size=$(docker images "$IMAGE_NAME:$TAG" --format "table {{.Size}}" | tail -n 1)
    print_status "Final image size: $image_size"
}

# Run main function
main "$@" 