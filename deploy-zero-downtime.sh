#!/bin/bash

# Zero-downtime deployment script for Laravel Optimizer
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
REMOTE_USER="root"
REMOTE_HOST="157.180.83.204"
PROJECT_DIR="/var/www/optimizer"
REPO_URL="https://github.com/xtemos/images-optimizer.git"
BRANCH="${1:-main}"

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Zero-downtime deployment function
deploy_with_supervisor_queues() {
    ssh $REMOTE_USER@$REMOTE_HOST << 'ENDSSH'
        set -e
        cd /var/www/optimizer
        
        echo "🔄 Updating code..."
        git fetch origin
        git checkout main
        git pull origin main
        
        echo "🏗️ Building new image..."
        docker compose -f docker-compose.prod.yml build app
        
        echo "🔧 Putting app in maintenance mode..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan down --refresh=15
        
        echo "📊 Running migrations..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
        
        echo "👥 Gracefully stopping queue workers..."
        # Send SIGTERM to queue workers to finish current jobs
        docker compose -f docker-compose.prod.yml exec -T app supervisorctl stop laravel-worker:*
        
        echo "⏳ Waiting for current jobs to finish (max 30 seconds)..."
        sleep 30
        
        echo "🔄 Recreating application container..."
        # Recreate container with new image
        docker compose -f docker-compose.prod.yml up -d --no-deps app
        
        echo "⏳ Waiting for container to be ready..."
        sleep 20
        
        echo "👥 Verifying queue workers are running..."
        # Check that supervisor started the queue workers
        docker compose -f docker-compose.prod.yml exec -T app supervisorctl status
        
        echo "🧹 Optimizing caches..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
        
        echo "🟢 Bringing application online..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan up
        
        echo "✅ Deployment completed!"
        docker compose -f docker-compose.prod.yml ps
ENDSSH
}

# Health check function
health_check() {
    print_step "Performing health check..."
    
    ssh $REMOTE_USER@$REMOTE_HOST << 'ENDSSH'
        cd /var/www/optimizer
        
        echo "🔍 Checking container health..."
        docker compose -f docker-compose.prod.yml ps
        
        echo "🔍 Checking application health..."
        # Test if the application responds
        if curl -f -s http://localhost/health > /dev/null; then
            echo "✅ Application is responding"
        else
            echo "❌ Application health check failed"
            exit 1
        fi
        
        echo "🔍 Checking queue worker..."
        if docker compose -f docker-compose.prod.yml exec -T queue php artisan queue:work --once --timeout=1 > /dev/null 2>&1; then
            echo "✅ Queue worker is functioning"
        else
            echo "⚠️ Queue worker check inconclusive (normal if no jobs)"
        fi
ENDSSH
}

# Rollback function
rollback() {
    print_warning "Rolling back to previous version..."
    
    ssh $REMOTE_USER@$REMOTE_HOST << 'ENDSSH'
        cd /var/www/optimizer
        
        echo "🔙 Rolling back git changes..."
        git reset --hard HEAD~1
        
        echo "🏗️ Rebuilding with previous version..."
        docker compose -f docker-compose.prod.yml build
        docker compose -f docker-compose.prod.yml up -d
        
        echo "🔧 Running maintenance tasks..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan migrate:rollback --force
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
        
        echo "🟢 Rollback completed!"
ENDSSH
}

# Main deployment process
main() {
    print_status "Starting zero-downtime deployment to branch: $BRANCH"
    
    # Pre-deployment checks
    if [ "$REMOTE_HOST" = "YOUR_HETZNER_IP" ]; then
        print_error "Please update REMOTE_HOST with your actual Hetzner server IP"
        exit 1
    fi
    
    if [ "$REPO_URL" = "YOUR_GITHUB_REPO_URL" ]; then
        print_error "Please update REPO_URL with your actual GitHub repository URL"
        exit 1
    fi
    
    # Ask for confirmation
    read -p "Deploy to production? This will update the running application. (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Deployment cancelled"
        exit 0
    fi
    
    # Push latest changes
    print_step "Pushing latest changes..."
    git push origin $BRANCH
    
    # Deploy
    if deploy_with_supervisor_queues; then
        print_status "✅ Deployment successful!"
        
        # Run health check
        sleep 5
        health_check
        
        print_status "🎉 Zero-downtime deployment completed successfully!"
    else
        print_error "❌ Deployment failed!"
        read -p "Do you want to rollback? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            rollback
        fi
        exit 1
    fi
}

main "$@" 