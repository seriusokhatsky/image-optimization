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

# Zero-downtime deployment with environment variables
deploy_zero_downtime() {
    print_status "Starting zero-downtime deployment with environment variable persistence..."
    
    ssh $REMOTE_USER@$REMOTE_HOST << 'ENDSSH'
        set -e
        cd /var/www/optimizer
        
        echo "🔄 Updating code..."
        git fetch origin
        git checkout main
        git pull origin main
        
        echo "💾 Preserving environment configuration..."
        # Check if .env exists and has APP_KEY, if not create it
        if [ ! -f ".env" ] || ! grep -q "^APP_KEY=base64:" .env; then
            echo "📝 Setting up production environment file..."
            cat > .env << 'ENVEOF'
# Production Environment Configuration
APP_NAME=Optimizer
APP_URL=https://img-optim.xtemos.com
LOG_LEVEL=info

# Database Configuration
DB_DATABASE=optimizer
DB_USERNAME=sail
DB_PASSWORD=CHANGE_THIS_PASSWORD

# Mail Configuration (update as needed)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS=noreply@img-optim.xtemos.com
MAIL_FROM_NAME=Optimizer
ENVEOF

            # Generate APP_KEY
            echo "🔑 Generating APP_KEY..."
            APP_KEY="base64:$(openssl rand -base64 32)"
            echo "APP_KEY=$APP_KEY" >> .env
            echo "✅ APP_KEY generated and set"
        else
            echo "✅ Environment file exists with valid APP_KEY"
        fi
        
        echo "🏗️ Building new image..."
        docker compose -f docker-compose.prod.yml build app
        
        echo "🔧 Putting app in maintenance mode..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan down --refresh=15 || echo "⚠️ Could not enable maintenance mode (app may not be running)"
        
        echo "📊 Running migrations..."
        # Try to run migrations on existing container first
        docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force || echo "⚠️ Migration on existing container failed, will retry after restart"
        
        echo "👥 Gracefully stopping queue workers..."
        # Send SIGTERM to queue workers to finish current jobs
        docker compose -f docker-compose.prod.yml exec -T app supervisorctl stop laravel-worker:* || echo "⚠️ Could not stop workers gracefully"
        
        echo "⏳ Waiting for current jobs to finish (max 15 seconds)..."
        sleep 15
        
        echo "🔄 Recreating application container with new image..."
        # Recreate container with new image while preserving environment
        docker compose -f docker-compose.prod.yml up -d --no-deps app
        
        echo "⏳ Waiting for container to be ready..."
        sleep 25
        
        echo "📊 Running migrations on new container..."
        # Run migrations on new container to ensure database is up to date
        docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
        
        echo "👥 Verifying queue workers are running..."
        # Check that supervisor started the queue workers
        docker compose -f docker-compose.prod.yml exec -T app supervisorctl status
        
        echo "🧹 Optimizing caches..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
        
        echo "🟢 Bringing application online..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan up
        
        echo "✅ Zero-downtime deployment completed!"
        echo "📊 Final container status:"
        docker compose -f docker-compose.prod.yml ps
ENDSSH
}

# Health check function
health_check() {
    print_step "Performing comprehensive health check..."
    
    ssh $REMOTE_USER@$REMOTE_HOST << 'ENDSSH'
        cd /var/www/optimizer
        
        echo "🔍 Checking container health..."
        docker compose -f docker-compose.prod.yml ps
        
        echo "🔍 Checking application health..."
        # Test if the application responds
        if curl -f -s -I http://localhost > /dev/null; then
            echo "✅ Application is responding"
        else
            echo "❌ Application health check failed"
            exit 1
        fi
        
        echo "🔍 Checking supervisor status..."
        docker compose -f docker-compose.prod.yml exec -T app supervisorctl status
        
        echo "🔍 Checking queue worker logs..."
        docker compose -f docker-compose.prod.yml exec -T app tail -n 5 /tmp/worker.log || echo "⚠️ No worker logs yet"
        
        echo "🔍 Testing Laravel functionality..."
        if docker compose -f docker-compose.prod.yml exec -T app php artisan --version > /dev/null; then
            echo "✅ Laravel is functioning properly"
        else
            echo "❌ Laravel command failed"
            exit 1
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
        docker compose -f docker-compose.prod.yml build app
        docker compose -f docker-compose.prod.yml up -d --no-deps app
        
        echo "⏳ Waiting for rollback container..."
        sleep 20
        
        echo "🔧 Running maintenance tasks..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan migrate:rollback --force || echo "⚠️ Migration rollback failed"
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
        docker compose -f docker-compose.prod.yml exec -T app php artisan up
        
        echo "🟢 Rollback completed!"
        docker compose -f docker-compose.prod.yml ps
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
    read -p "Deploy to production? This will update the running application with zero downtime. (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Deployment cancelled"
        exit 0
    fi
    
    # Push latest changes
    print_step "Pushing latest changes..."
    git push origin $BRANCH
    
    # Deploy
    if deploy_zero_downtime; then
        print_status "✅ Deployment successful!"
        
        # Run health check
        sleep 5
        if health_check; then
            print_status "🎉 Zero-downtime deployment completed successfully!"
            print_status "🌐 Your application is now live at: https://img-optim.xtemos.com"
        else
            print_warning "⚠️ Deployment completed but health check failed"
            read -p "Do you want to rollback? (y/N): " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                rollback
            fi
        fi
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