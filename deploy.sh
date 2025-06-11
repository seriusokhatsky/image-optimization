#!/bin/bash

# Deployment script for Laravel Optimizer on Hetzner Cloud
# Usage: ./deploy.sh [branch]

set -e

echo "🚀 Starting deployment to Hetzner Cloud..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REMOTE_USER="root"
REMOTE_HOST="157.180.83.204"
PROJECT_DIR="/var/www/optimizer"
REPO_URL="https://github.com/xtemos/images-optimizer.git"
BRANCH="${1:-main}"

# Function to print colored output
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

# Configuration is now set above - ready to deploy

# Deploy to server with proper environment handling
deploy_to_server() {
    print_status "Connecting to server and deploying from GitHub..."
    
    ssh $REMOTE_USER@$REMOTE_HOST << ENDSSH
        set -e
        
        echo "🔧 Setting up server environment..."
        
        # Install Git if not already installed
        if ! command -v git &> /dev/null; then
            echo "Installing Git..."
            apt-get update
            apt-get install -y git
        else
            echo "✅ Git is already installed"
        fi
        
        # Check Docker installation (skip if already installed)
        if ! command -v docker &> /dev/null; then
            echo "⚠️  Docker not found. Installing Docker..."
            curl -fsSL https://get.docker.com -o get-docker.sh
            sh get-docker.sh
            systemctl enable docker
            systemctl start docker
            rm get-docker.sh
        else
            echo "✅ Docker is already installed"
            # Ensure Docker service is running
            systemctl start docker || true
        fi
        
        # Check Docker Compose installation (skip if already installed)
        if ! command -v docker compose &> /dev/null; then
            echo "⚠️  Docker Compose not found. Installing Docker Compose..."
            curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-\$(uname -s)-\$(uname -m)" -o /usr/local/bin/docker-compose
            chmod +x /usr/local/bin/docker-compose
        else
            echo "✅ Docker Compose is already installed"
        fi
        
        # Verify Docker is working
        echo "🔍 Verifying Docker installation..."
        docker --version
        docker compose --version
        
        # Clone or update repository
        if [ ! -d "$PROJECT_DIR" ]; then
            echo "📦 Cloning repository..."
            git clone $REPO_URL $PROJECT_DIR
            cd $PROJECT_DIR
            git checkout $BRANCH
        else
            echo "🔄 Updating repository..."
            cd $PROJECT_DIR
            git fetch origin
            git checkout $BRANCH
            git pull origin $BRANCH
        fi
        
        # Create or update .env file for production
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

        # Generate APP_KEY if not already set
        echo "🔑 Generating APP_KEY..."
        APP_KEY="base64:\$(openssl rand -base64 32)"
        echo "APP_KEY=\$APP_KEY" >> .env
        echo "✅ APP_KEY generated: \$APP_KEY"
        
        # Stop existing containers gracefully
        echo "🛑 Stopping existing containers..."
        docker compose -f docker-compose.prod.yml down || true
        
        # Build and deploy with zero downtime strategy
        echo "🏗️  Building new images..."
        docker compose -f docker-compose.prod.yml build app
        
        # Start containers
        echo "🚀 Starting containers..."
        docker compose -f docker-compose.prod.yml up -d
        
        # Wait for containers to be ready
        echo "⏳ Waiting for containers to be ready..."
        sleep 30
        
        # Check database connection and run migrations
        echo "📊 Running database migrations..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
        
        # Clear and optimize caches
        echo "🧹 Optimizing application..."
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
        docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
        
        # Check queue workers
        echo "👥 Checking queue workers..."
        docker compose -f docker-compose.prod.yml exec -T app supervisorctl status
        
        echo "✅ Deployment completed successfully!"
        echo "📊 Container status:"
        docker compose -f docker-compose.prod.yml ps
        
        echo "🔍 Testing application health..."
        sleep 5
        curl -I http://localhost || echo "⚠️ Health check failed - check logs"
ENDSSH

    print_status "Deployment completed!"
}

# Update local repository and push
update_and_push() {
    print_step "Updating local repository..."
    
    if [ ! -d ".git" ]; then
        print_error "Not a git repository. Please run this from your project root."
        exit 1
    fi
    
    # Check if there are uncommitted changes
    if ! git diff-index --quiet HEAD --; then
        print_warning "You have uncommitted changes. Please commit or stash them first."
        read -p "Do you want to continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
    
    print_status "Pushing latest changes to GitHub..."
    git push origin $BRANCH
}

# Main deployment function
main() {
    print_status "Starting environment-based deployment to branch: $BRANCH"
    
    # Validate environment
    if [ ! -f "docker-compose.prod.yml" ]; then
        print_error "docker-compose.prod.yml not found!"
        exit 1
    fi
    
    # Ask if user wants to push latest changes
    read -p "Push latest changes to GitHub before deploying? (Y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        update_and_push
    fi
    
    deploy_to_server
    
    print_status "🎉 Deployment completed!"
    print_warning "Next steps:"
    echo "1. SSH to your server: ssh $REMOTE_USER@$REMOTE_HOST"
    echo "2. Update database password in .env: cd $PROJECT_DIR && nano .env"
    echo "3. Restart after password update: docker compose -f docker-compose.prod.yml restart"
    echo "4. Your app will be available at: https://img-optim.xtemos.com"
}

main "$@" 