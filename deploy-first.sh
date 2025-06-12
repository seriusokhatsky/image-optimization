#!/bin/bash

# First deployment script for Laravel Optimizer
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
GIT_REPO="https://github.com/seriusokhatsky/image-optimization.git"  # Update with your actual repo

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# First deployment function
first_deploy() {
    print_status "Starting first deployment..."

    # 1. Generate APP_KEY locally and create a script to set it remotely.
    #    This is the most reliable way to handle special characters.
    print_status "🔑 Generating Laravel application key locally..."
    LOCAL_APP_KEY="base64:$(openssl rand -base64 32)"
    KEY_SCRIPT_PATH="/tmp/set_optimizer_key.sh"

    # Create the script content. The $LOCAL_APP_KEY is expanded here, locally.
    cat > "$KEY_SCRIPT_PATH" << EOF
#!/bin/sh
set -e
ENV_FILE="/var/www/optimizer/.env"
echo "🚀 Setting APP_KEY in \$ENV_FILE on remote server..."
# Use grep to remove the old key and echo to add the new one.
grep -v "^APP_KEY=" "\$ENV_FILE" > "\$ENV_FILE.tmp"
echo "APP_KEY=$LOCAL_APP_KEY" >> "\$ENV_FILE.tmp"
mv "\$ENV_FILE.tmp" "\$ENV_FILE"
echo "✅ APP_KEY has been set."
EOF

    print_status "⬆️  Uploading key-setting script to server..."
    scp "$KEY_SCRIPT_PATH" "$REMOTE_USER@$REMOTE_HOST:/tmp/set_optimizer_key.sh"
    rm "$KEY_SCRIPT_PATH" # Clean up local temp script

    # 2. Connect via SSH.
    #    The heredoc is NOT quoted (`<< ENDSSH`) to allow $GIT_REPO to be expanded locally.
    #    All variables for remote execution must be escaped with a backslash (\$).
    ssh $REMOTE_USER@$REMOTE_HOST << ENDSSH
        set -e
        
        echo "📁 Creating project directory..."
        mkdir -p /var/www
        cd /var/www
        
        echo "📥 Cloning repository..."
        if [ -d "optimizer" ]; then
            echo "⚠️  Directory exists, removing old version..."
            rm -rf optimizer
        fi
        git clone $GIT_REPO optimizer # This $GIT_REPO is expanded locally
        cd optimizer
        
        echo "📝 Setting up environment file..."
        if [ ! -f .env ]; then
            echo "Creating production .env file..."
            cat > .env << 'ENVEOF'
APP_NAME="Optimizer"
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://img-optim.xtemos.com

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=optimizer
DB_USERNAME=sail
DB_PASSWORD=CHANGE_THIS_PASSWORD

CACHE_DRIVER=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
BROADCAST_DRIVER=log

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@img-optim.xtemos.com
MAIL_FROM_NAME="Optimizer"
ENVEOF
            echo "✅ Created production .env file"
        else
            echo "✅ .env file already exists, using existing file"
        fi
        
        # 3. Execute the key-setting script that was uploaded.
        echo "🔑 Executing the remote key-setting script..."
        chmod +x /tmp/set_optimizer_key.sh
        /tmp/set_optimizer_key.sh
        rm /tmp/set_optimizer_key.sh # Clean up remote script
        
        echo "🔍 Verifying final APP_KEY in file:"
        grep "^APP_KEY=" .env
        
        echo "🏗️ Building Docker image..."
        # These variables must be escaped (\$) to be evaluated on the remote server.
        BUILD_TIMESTAMP=\$(date +%s)
        GIT_COMMIT=\$(git rev-parse HEAD)
        
        docker build \\
            --target production \\
            --build-arg BUILD_TIMESTAMP=\$BUILD_TIMESTAMP \\
            --build-arg CACHEBUST=\$GIT_COMMIT \\
            -t optimizer-app:production \\
            -f docker/production/Dockerfile \\
            .
        
        echo "🚀 Starting containers..."
        docker compose -f docker-compose.prod.yml up -d
        
        echo "⏳ Waiting for containers to be ready..."
        sleep 10
        
        echo "📦 Running initial Laravel setup..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
        docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
        docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
        docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache
        
        echo "🔍 Verifying deployment..."
        docker compose -f docker-compose.prod.yml ps
        
        echo "🌐 Testing application..."
        docker compose -f docker-compose.prod.yml exec -T app curl -f -s -I http://localhost && echo "✅ Local test passed"
        
        echo "🌍 Testing external access..."
        # This variable must be escaped (\$) to be evaluated on the remote server.
        HTTP_CODE=\$(curl -s -o /dev/null -w "%{http_code}" https://img-optim.xtemos.com/ || echo "000")
        echo "External response: HTTP \$HTTP_CODE"
        
        if [ "\$HTTP_CODE" = "200" ]; then
            echo "✅ External access working!"
        else
            echo "⚠️  External access may need configuration"
        fi
        
        echo "✅ First deployment completed!"
        echo ""
        echo "📋 Next steps:"
        echo "1. Update .env file with your production database credentials"
        echo "   - Change DB_PASSWORD from 'CHANGE_THIS_PASSWORD' to a secure password"
        echo "   - Update MAIL_* settings if email functionality is needed"
        echo "2. Configure your domain/DNS to point to this server"
        echo "3. Set up SSL certificates if needed"
        echo "4. Configure any additional services (Redis, etc.)"
        echo ""
        echo "💡 The APP_KEY was generated before container startup and saved to .env"
ENDSSH
}

# Main execution
main() {
    # Basic checks
    if [ "$REMOTE_HOST" = "157.180.83.204" ]; then
        print_warning "Using default IP. Update REMOTE_HOST if needed."
    fi
    
    if [[ "$GIT_REPO" == *"YOUR_USERNAME"* ]]; then
        print_error "Please update GIT_REPO with your actual repository URL"
        exit 1
    fi
    
    # Confirmation
    echo "This will perform the FIRST deployment to the server."
    echo "Server: $REMOTE_HOST"
    echo "Directory: $PROJECT_DIR"
    echo ""
    read -p "Continue with first deployment? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Deployment cancelled"
        exit 0
    fi
    
    # Copy SSL certificates first
    print_status "🔐 Copying SSL certificates to server..."
    if [ -f "copy-ssl.sh" ]; then
        chmod +x copy-ssl.sh
        if ./copy-ssl.sh; then
            print_status "✅ SSL certificates copied successfully"
        else
            print_error "❌ Failed to copy SSL certificates"
            exit 1
        fi
    else
        print_warning "copy-ssl.sh not found, skipping SSL setup"
        print_warning "You'll need to set up SSL certificates manually later"
    fi
    
    # Deploy
    if first_deploy; then
        print_status "🎉 First deployment successful!"
        print_status "🌐 Application should be available at: https://img-optim.xtemos.com"
        print_status ""
        print_status "📝 Don't forget to:"
        print_status "   - Update .env with production database password"
        print_status "   - Configure DNS/domain settings"
        print_status "   - Set up SSL certificates"
        print_status ""
        print_status "✨ APP_KEY has been automatically generated and persisted!"
    else
        print_error "❌ First deployment failed!"
        exit 1
    fi
}

main "$@" 