#!/bin/bash

# Simple deployment script for Laravel Optimizer
set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Configuration
REMOTE_USER="root"
REMOTE_HOST="157.180.83.204"
PROJECT_DIR="/var/www/optimizer"

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Main deployment function
deploy() {
    print_status "Starting simple deployment..."
    
    ssh $REMOTE_USER@$REMOTE_HOST << 'ENDSSH'
        set -e
        cd /var/www/optimizer
        
        echo "🔄 Updating code..."
        git fetch origin
        git checkout main
        git pull origin main
        
        echo "🏗️ Building new Docker image with cache busting..."
        BUILD_TIMESTAMP=$(date +%s)
        GIT_COMMIT=$(git rev-parse HEAD)
        
        docker build \
            --target production \
            --build-arg BUILD_TIMESTAMP=$BUILD_TIMESTAMP \
            --build-arg CACHEBUST=$GIT_COMMIT \
            -t optimizer-app:production \
            -f docker/production/Dockerfile \
            .
        
        echo "🔧 Enabling maintenance mode..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan down --refresh=15 || echo "App may not be running yet"
        
        echo "🚀 Deploying new containers..."
        docker compose -f docker-compose.prod.yml up -d --no-deps app
        
        echo "📦 Running Laravel maintenance tasks..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
        docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
        docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
        docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache
        
        echo "✅ Disabling maintenance mode..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan up
        
        echo "🔍 Verifying deployment..."
        docker compose -f docker-compose.prod.yml ps
        
        echo "🌐 Testing application..."
        docker compose -f docker-compose.prod.yml exec -T app curl -f -s -I http://localhost && echo "✅ Local test passed"
        
        echo "🌍 Testing external access..."
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://img-optim.xtemos.com/)
        echo "External response: HTTP $HTTP_CODE"
        
        echo "✅ Deployment completed!"
ENDSSH
}

# Main execution
main() {
    # Basic checks
    if [ "$REMOTE_HOST" = "YOUR_HETZNER_IP" ]; then
        print_error "Please update REMOTE_HOST with your actual server IP"
        exit 1
    fi
    
    # Confirmation
    read -p "Deploy to production? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Deployment cancelled"
        exit 0
    fi
    
    # Push changes first
    print_status "Pushing latest changes..."
    git push origin main
    
    # Deploy
    if deploy; then
        print_status "🎉 Deployment successful!"
        print_status "🌐 Application: https://img-optim.xtemos.com"
    else
        print_error "❌ Deployment failed!"
        exit 1
    fi
}

main "$@" 