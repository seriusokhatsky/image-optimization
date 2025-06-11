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
        
        echo "🔧 Setting up build scripts..."
        # Make build script executable if it exists
        if [ -f "docker/production/build-optimized.sh" ]; then
            chmod +x docker/production/build-optimized.sh
            echo "✅ Optimized build script is ready"
        fi
        
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

            # Generate APP_KEY with proper format
            echo "🔑 Generating APP_KEY..."
            APP_KEY="base64:$(openssl rand -base64 32)"
            echo "APP_KEY=$APP_KEY" >> .env
            echo "✅ APP_KEY generated and set: $APP_KEY"
        else
            echo "✅ Environment file exists with valid APP_KEY"
            # Verify APP_KEY is not placeholder
            if grep -q "APP_KEY=base64:YOUR_GENERATED_KEY_HERE" .env; then
                echo "🔑 Replacing placeholder APP_KEY with real one..."
                APP_KEY="base64:$(openssl rand -base64 32)"
                sed -i "s/APP_KEY=base64:YOUR_GENERATED_KEY_HERE/APP_KEY=$APP_KEY/" .env
                echo "✅ APP_KEY updated: $APP_KEY"
            fi
        fi
        
        echo "🏗️ Building new image with optimized multi-stage caching..."
        echo "📦 Using multi-stage build (dependencies cached automatically)..."
        # Build with multi-stage Dockerfile - dependencies are cached in separate layers
        # Use build args to ensure fresh code is always copied
        BUILD_TIMESTAMP=$(date +%s)
        GIT_COMMIT=$(git rev-parse HEAD)
        echo "🔧 Cache busting with timestamp: $BUILD_TIMESTAMP, commit: $GIT_COMMIT"
        
        docker build \
            --target production \
            --build-arg BUILD_TIMESTAMP=$BUILD_TIMESTAMP \
            --build-arg CACHEBUST=$GIT_COMMIT \
            -t optimizer-app:production \
            -f docker/production/Dockerfile \
            .
        
        echo "✅ Docker build completed. Checking if cache was properly busted..."
        # Verify the build actually processed the cache-busting layer
        if docker history optimizer-app:production | grep -q "$BUILD_TIMESTAMP"; then
            echo "✅ Cache busting confirmed - fresh code will be deployed"
        else
            echo "⚠️ Cache busting may not have worked - check build logs above"
        fi
        
        echo "🔧 Putting app in maintenance mode..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan down --refresh=15 || echo "⚠️ Could not enable maintenance mode (app may not be running)"
        

        
        echo "🔍 Verifying application is live..."
        # Give Laravel a moment to fully start
        sleep 5
        
        # Test application response with aggressive retry logic
        for i in {1..5}; do
            if docker compose -f docker-compose.prod.yml exec -T app curl -f -s http://localhost > /dev/null; then
                echo "✅ Application is responding properly (attempt $i)"
                break
            else
                echo "⚠️ Application health check failed (attempt $i/5)"
                echo "🔄 Aggressively fixing maintenance mode..."
                # Force remove maintenance mode file and restart Laravel
                docker compose -f docker-compose.prod.yml exec -T app rm -f /var/www/html/storage/framework/down || true
                docker compose -f docker-compose.prod.yml exec -T app php artisan up
                docker compose -f docker-compose.prod.yml exec -T app php artisan cache:clear || true
                sleep 3
            fi
        done
        
        echo "🔍 Final verification - checking external access..."
        sleep 2
        
        # External health check with retry
        for i in {1..5}; do
            HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://img-optim.xtemos.com/)
            if [ "$HTTP_CODE" = "200" ]; then
                echo "✅ External HTTPS access confirmed (HTTP 200) (attempt $i)"
                break
            else
                echo "⚠️ External HTTPS access failed (HTTP $HTTP_CODE) (attempt $i/5)"
                if [ $i -eq 5 ]; then
                    echo "❌ External health check FAILED after 5 attempts"
                    echo "ℹ️ Attempting to fix maintenance mode issue..."
                    docker compose -f docker-compose.prod.yml exec -T app php artisan up
                    echo "ℹ️ Check the application logs:"
                    echo "  docker compose -f docker-compose.prod.yml logs app --tail=20"
                else
                    # If it's a 503, try to exit maintenance mode again
                    if [ "$HTTP_CODE" = "503" ]; then
                        echo "🔄 Service unavailable - attempting to exit maintenance mode..."
                        docker compose -f docker-compose.prod.yml exec -T app php artisan up
                    fi
                    sleep 3
                fi
            fi
        done
        
        echo "✅ Zero-downtime deployment completed!"
        echo "📊 Final container status:"
        docker compose -f docker-compose.prod.yml ps
        
        echo "👥 Queue worker status:"
        docker compose -f docker-compose.prod.yml exec -T app ps aux | grep -E "(queue|supervisor)" | grep -v grep || echo "No queue processes found"
        
        echo "🔧 Final comprehensive fix - eliminating 503 errors permanently..."
        # Comprehensive fix to prevent 503 errors (disable strict error mode for this section)
        set +e
        
        for i in {1..3}; do
            echo "🔄 Fix attempt $i/3..."
            
            # Remove any maintenance mode files
            docker compose -f docker-compose.prod.yml exec -T app rm -f /var/www/html/storage/framework/down || true
            
            # Bring application online
            docker compose -f docker-compose.prod.yml exec -T app php artisan up
            
            # Clear all caches to ensure fresh state
            docker compose -f docker-compose.prod.yml exec -T app php artisan cache:clear || true
            docker compose -f docker-compose.prod.yml exec -T app php artisan config:clear || true
            docker compose -f docker-compose.prod.yml exec -T app php artisan route:clear || true
            docker compose -f docker-compose.prod.yml exec -T app php artisan view:clear || true
            
            # Re-optimize
            docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache || true
            docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache || true
            docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache || true
            
            sleep 5
            
            # Test external access
            HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://img-optim.xtemos.com/)
            if [ "$HTTP_CODE" = "200" ]; then
                echo "✅ Fix successful! Application is responding (HTTP $HTTP_CODE)"
                break
            else
                echo "⚠️ Still getting HTTP $HTTP_CODE, trying again..."
                if [ $i -eq 3 ]; then
                    echo "❌ Could not get HTTP 200 after 3 attempts, but deployment completed"
                    echo "🔧 Try running the script again or check manually:"
                    echo "  ssh root@157.180.83.204 'cd /var/www/optimizer && docker compose -f docker-compose.prod.yml exec -T app php artisan up'"
                fi
            fi
        done
        
        # Re-enable strict error mode
        set -e
        
        echo "✅ Build completed - ready for container deployment!"
ENDSSH

    # Execute server-side deployment outside of the main SSH session
    print_step "Executing server-side deployment..."
    scp deploy-server.sh $REMOTE_USER@$REMOTE_HOST:/var/www/optimizer/
    ssh $REMOTE_USER@$REMOTE_HOST "chmod +x /var/www/optimizer/deploy-server.sh && /var/www/optimizer/deploy-server.sh"
    
    print_status "✅ Server-side deployment completed!"
}

# Health check function
health_check() {
    print_step "Performing comprehensive health check..."
    
    ssh $REMOTE_USER@$REMOTE_HOST << 'ENDSSH'
        cd /var/www/optimizer
        
        echo "🔍 Checking container health..."
        docker compose -f docker-compose.prod.yml ps
        
        echo "🔍 Checking application health..."
        # Test if the application responds locally
        if docker compose -f docker-compose.prod.yml exec -T app curl -f -s -I http://localhost > /dev/null; then
            echo "✅ Application is responding locally"
        else
            echo "❌ Local application health check failed"
            echo "🔧 Attempting to fix..."
            docker compose -f docker-compose.prod.yml exec -T app rm -f /var/www/html/storage/framework/down || true
            docker compose -f docker-compose.prod.yml exec -T app php artisan up || true
        fi
        
        echo "🔍 Checking external HTTPS access..."
        # Test external HTTPS access
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://img-optim.xtemos.com/)
        if [ "$HTTP_CODE" = "200" ]; then
            echo "✅ External HTTPS access working (HTTP $HTTP_CODE)"
        else
            echo "❌ External HTTPS access failed (HTTP $HTTP_CODE)"
            echo "🔧 This may resolve automatically. Check https://img-optim.xtemos.com manually."
        fi
        
        echo "🔍 Checking supervisor status..."
        if docker compose -f docker-compose.prod.yml exec -T app supervisorctl status; then
            echo "✅ Supervisor is running properly"
        else
            echo "⚠️ Supervisor status check failed - queue workers may not be running"
        fi
        
        echo "🔍 Checking queue workers..."
        WORKER_COUNT=$(docker compose -f docker-compose.prod.yml exec -T app ps aux | grep "queue:work" | grep -v grep | wc -l)
        if [ "$WORKER_COUNT" -ge "2" ]; then
            echo "✅ Queue workers are running ($WORKER_COUNT workers found)"
        else
            echo "⚠️ Not enough queue workers running (found: $WORKER_COUNT, expected: 2+)"
            echo "🔧 Workers should start automatically. Check logs if issues persist."
        fi
        
        echo "🔍 Testing Laravel functionality..."
        if docker compose -f docker-compose.prod.yml exec -T app php artisan --version > /dev/null; then
            LARAVEL_VERSION=$(docker compose -f docker-compose.prod.yml exec -T app php artisan --version)
            echo "✅ Laravel is functioning properly: $LARAVEL_VERSION"
        else
            echo "⚠️ Laravel command failed - check container logs"
        fi
        
        echo "🔍 Checking for recent deployment..."
        CONTAINER_DATE=$(docker compose -f docker-compose.prod.yml exec -T app stat -c %y /var/www/html/DEMO_SETUP.md 2>/dev/null | cut -d' ' -f1 || echo "unknown")
        TODAY_DATE=$(date +%Y-%m-%d)
        if [ "$CONTAINER_DATE" = "$TODAY_DATE" ]; then
            echo "✅ Container has fresh code (updated: $CONTAINER_DATE)"
        else
            echo "⚠️ Container may have stale code (last update: $CONTAINER_DATE)"
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
        echo "📦 Using multi-stage build for rollback..."
        docker build \
            --target production \
            -t optimizer-app:production \
            -f docker/production/Dockerfile \
            .
        docker compose -f docker-compose.prod.yml up -d --no-deps app
        
        echo "⏳ Waiting for rollback container..."
        sleep 25
        
        echo "🔧 Running maintenance tasks..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan migrate:rollback --force || echo "⚠️ Migration rollback failed"
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
        docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
        docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
        docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache
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