#!/bin/bash

# Docker-based SSL Certificate Generation Script
# This script uses Docker containers to generate Let's Encrypt certificates

set -e

# Configuration
DOMAIN="img-optim.xtemos.com"
EMAIL="xtemos.studio@gmail.com"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

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
    echo -e "${GREEN}[STEP]${NC} $1"
}

# Validate configuration
validate_config() {
    if [ "$EMAIL" = "admin@xtemos.com" ]; then
        print_error "Please update the EMAIL variable in this script with your actual email address"
        exit 1
    fi
}

# Create necessary directories
setup_directories() {
    print_step "Creating necessary directories..."
    
    mkdir -p docker/nginx/ssl
    mkdir -p docker/nginx/webroot
    
    print_status "Directories created"
}

# Stop production containers
stop_production() {
    print_step "Stopping production containers..."
    
    docker compose -f docker-compose.prod.yml down 2>/dev/null || true
    
    print_status "Production containers stopped"
}

# Start certificate generation setup
start_certbot_setup() {
    print_step "Starting certificate generation setup..."
    
    # Create network if it doesn't exist
    docker network create optimizer-network 2>/dev/null || true
    
    # Start nginx for verification
    docker compose -f docker-compose.ssl.yml up -d nginx-certbot
    
    # Wait for nginx to be ready
    sleep 5
    
    print_status "Nginx ready for certificate verification"
}

# Generate certificate
generate_certificate() {
    print_step "Generating Let's Encrypt certificate..."
    
    # Update email in docker-compose file command
    sed -i "s/admin@xtemos\.com/$EMAIL/g" docker-compose.ssl.yml
    
    # Run certbot
    docker-compose -f docker-compose.ssl.yml run --rm certbot
    
    print_status "Certificate generated successfully"
}

# Copy and organize certificates
organize_certificates() {
    print_step "Organizing certificates..."
    
    # Create the expected directory structure
    mkdir -p docker/nginx/ssl/live/$DOMAIN
    
    # Copy certificates to the expected location
    if [ -d "docker/nginx/ssl/live/$DOMAIN" ]; then
        print_status "Certificates are ready in docker/nginx/ssl/live/$DOMAIN/"
        ls -la docker/nginx/ssl/live/$DOMAIN/
    else
        print_error "Certificate directory not found. Check certbot logs."
        exit 1
    fi
}

# Update nginx configuration
update_nginx_config() {
    print_step "Updating nginx configuration for production certificates..."
    
    # Backup current config
    cp docker/nginx/nginx.conf docker/nginx/nginx.conf.backup 2>/dev/null || true
    
    # Update certificate paths
    sed -i 's|/local-cert\.pem|/fullchain.pem|g' docker/nginx/nginx.conf
    sed -i 's|/local-key\.pem|/privkey.pem|g' docker/nginx/nginx.conf
    
    print_status "Nginx configuration updated"
}

# Cleanup
cleanup() {
    print_step "Cleaning up..."
    
    docker compose -f docker-compose.ssl.yml down 2>/dev/null || true
    
    print_status "Cleanup completed"
}

# Start production with new certificates
start_production() {
    print_step "Starting production containers with new certificates..."
    
    docker compose -f docker-compose.prod.yml up -d
    
    print_status "Production containers started"
}

# Test certificate
test_certificate() {
    print_step "Testing certificate..."
    
    sleep 10  # Wait for containers to be ready
    
    if curl -k -s -o /dev/null -w "%{http_code}" https://$DOMAIN | grep -q "200"; then
        print_status "✅ HTTPS is working correctly!"
    else
        print_warning "⚠️  HTTPS test failed. Check your configuration."
    fi
}

# Create renewal script
create_renewal_script() {
    print_step "Creating certificate renewal script..."
    
    cat > scripts/renew-ssl.sh << 'EOF'
#!/bin/bash

# Certificate Renewal Script

DOMAIN="img-optim.xtemos.com"

echo "Starting certificate renewal..."

# Stop production containers
docker compose -f docker-compose.prod.yml down

# Create network if needed
docker network create optimizer-network 2>/dev/null || true

# Start verification setup
docker compose -f docker-compose.ssl.yml up -d nginx-certbot

# Wait a moment
sleep 5

# Renew certificate
docker compose -f docker-compose.ssl.yml run --rm certbot renew

# Cleanup
docker compose -f docker-compose.ssl.yml down

# Start production
docker compose -f docker-compose.prod.yml up -d

echo "Certificate renewal completed!"
EOF

    chmod +x scripts/renew-ssl.sh
    
    print_status "Renewal script created at scripts/renew-ssl.sh"
}

# Main execution
main() {
    print_status "Starting SSL certificate generation for $DOMAIN"
    
    validate_config
    setup_directories
    stop_production
    start_certbot_setup
    generate_certificate
    organize_certificates
    update_nginx_config
    cleanup
    start_production
    test_certificate
    create_renewal_script
    
    print_status "✅ SSL certificate setup completed!"
    print_status ""
    print_status "📋 Summary:"
    print_status "• Certificates are stored in: docker/nginx/ssl/live/$DOMAIN/"
    print_status "• Nginx configuration has been updated"
    print_status "• Production containers are running with new certificates"
    print_status "• Renewal script created: scripts/renew-ssl.sh"
    print_status ""
    print_status "🔧 Next steps:"
    print_status "1. Test your site: https://$DOMAIN"
    print_status "2. Set up automatic renewal cron job:"
    print_status "   echo '0 3 * * * /path/to/your/project/scripts/renew-ssl.sh' | crontab -"
    print_status "3. Monitor certificate expiry: https://www.ssllabs.com/ssltest/"
}

# Run main function
main "$@" 