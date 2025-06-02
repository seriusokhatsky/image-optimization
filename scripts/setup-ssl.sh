#!/bin/bash

# SSL Certificate Setup Script for Production
# This script sets up Let's Encrypt SSL certificates for your domain

set -e

# Configuration
DOMAIN="img-optim.xtemos.com"
EMAIL="xtemos.studio@gmail.com"
SSL_DIR="./docker/nginx/ssl"
WEBROOT_PATH="./docker/nginx/webroot"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

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

# Check if running as root (required for certbot)
check_root() {
    if [ "$EUID" -ne 0 ]; then
        print_error "This script must be run as root (use sudo)"
        print_status "Example: sudo ./scripts/setup-ssl.sh"
        exit 1
    fi
}

# Install certbot if not already installed
install_certbot() {
    print_step "Installing certbot..."
    
    if command -v certbot &> /dev/null; then
        print_status "Certbot is already installed"
        return
    fi
    
    # Install snapd if not available
    if ! command -v snap &> /dev/null; then
        apt update
        apt install -y snapd
    fi
    
    # Install certbot via snap
    snap install core; snap refresh core
    snap install --classic certbot
    
    # Create symlink
    ln -sf /snap/bin/certbot /usr/bin/certbot
    
    print_status "Certbot installed successfully"
}

# Create webroot directory for HTTP-01 challenge
setup_webroot() {
    print_step "Setting up webroot for verification..."
    
    mkdir -p "$WEBROOT_PATH"
    chmod 755 "$WEBROOT_PATH"
    
    print_status "Webroot created at $WEBROOT_PATH"
}

# Temporarily modify nginx config for certificate verification
setup_temp_nginx() {
    print_step "Setting up temporary nginx configuration..."
    
    # Create temporary nginx config for verification
    cat > ./docker/nginx/nginx-temp.conf << EOF
events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    sendfile on;
    keepalive_timeout 65;

    # Temporary server for Let's Encrypt verification
    server {
        listen 80;
        server_name $DOMAIN;

        # Allow Let's Encrypt verification
        location /.well-known/acme-challenge/ {
            root /var/www/html;
        }

        # Redirect everything else to HTTPS (will be used after cert is generated)
        location / {
            return 301 https://\$server_name\$request_uri;
        }
    }
}
EOF

    print_status "Temporary nginx config created"
}

# Generate Let's Encrypt certificate
generate_certificate() {
    print_step "Generating Let's Encrypt certificate..."
    
    # Stop nginx container if running
    print_status "Stopping nginx container..."
    docker compose -f docker-compose.prod.yml stop nginx 2>/dev/null || true
    
    # Generate certificate using standalone mode
    certbot certonly \
        --standalone \
        --non-interactive \
        --agree-tos \
        --email "$EMAIL" \
        -d "$DOMAIN" \
        --cert-path "$SSL_DIR/live/$DOMAIN/cert.pem" \
        --key-path "$SSL_DIR/live/$DOMAIN/privkey.pem" \
        --fullchain-path "$SSL_DIR/live/$DOMAIN/fullchain.pem" \
        --chain-path "$SSL_DIR/live/$DOMAIN/chain.pem"
    
    print_status "Certificate generated successfully"
}

# Copy certificates to nginx directory
copy_certificates() {
    print_step "Copying certificates to nginx directory..."
    
    # Create target directory
    mkdir -p "$SSL_DIR/live/$DOMAIN"
    
    # Copy certificates from Let's Encrypt directory
    cp "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" "$SSL_DIR/live/$DOMAIN/fullchain.pem"
    cp "/etc/letsencrypt/live/$DOMAIN/privkey.pem" "$SSL_DIR/live/$DOMAIN/privkey.pem"
    cp "/etc/letsencrypt/live/$DOMAIN/cert.pem" "$SSL_DIR/live/$DOMAIN/cert.pem"
    cp "/etc/letsencrypt/live/$DOMAIN/chain.pem" "$SSL_DIR/live/$DOMAIN/chain.pem"
    
    # Set proper permissions
    chmod 644 "$SSL_DIR/live/$DOMAIN/fullchain.pem"
    chmod 600 "$SSL_DIR/live/$DOMAIN/privkey.pem"
    chmod 644 "$SSL_DIR/live/$DOMAIN/cert.pem"
    chmod 644 "$SSL_DIR/live/$DOMAIN/chain.pem"
    
    print_status "Certificates copied and permissions set"
}

# Update nginx configuration for production
update_nginx_config() {
    print_step "Updating nginx configuration for production certificates..."
    
    # Backup current config
    cp docker/nginx/nginx.conf docker/nginx/nginx.conf.backup
    
    # Update certificate paths in nginx config
    sed -i "s|local-cert.pem|fullchain.pem|g" docker/nginx/nginx.conf
    sed -i "s|local-key.pem|privkey.pem|g" docker/nginx/nginx.conf
    
    print_status "Nginx configuration updated"
}

# Setup automatic renewal
setup_renewal() {
    print_step "Setting up automatic certificate renewal..."
    
    # Create renewal script
    cat > /usr/local/bin/renew-ssl.sh << EOF
#!/bin/bash

# Stop nginx
docker compose -f /path/to/your/project/docker-compose.prod.yml stop nginx

# Renew certificate
certbot renew --quiet

# Copy renewed certificates
cp "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" "/path/to/your/project/$SSL_DIR/live/$DOMAIN/fullchain.pem"
cp "/etc/letsencrypt/live/$DOMAIN/privkey.pem" "/path/to/your/project/$SSL_DIR/live/$DOMAIN/privkey.pem"

# Restart nginx
docker compose -f /path/to/your/project/docker-compose.prod.yml start nginx

echo "SSL certificates renewed and nginx restarted"
EOF

    chmod +x /usr/local/bin/renew-ssl.sh
    
    # Add to crontab for automatic renewal (runs twice daily)
    (crontab -l 2>/dev/null; echo "0 0,12 * * * /usr/local/bin/renew-ssl.sh") | crontab -
    
    print_status "Automatic renewal configured"
}

# Main execution
main() {
    print_status "Starting SSL certificate setup for $DOMAIN"
    
    # Validate inputs
    if [ -z "$EMAIL" ] || [ "$EMAIL" = "admin@xtemos.com" ]; then
        print_error "Please update the EMAIL variable in this script with your actual email address"
        exit 1
    fi
    
    # Check if domain resolves to this server
    print_status "Checking if domain resolves to this server..."
    DOMAIN_IP=$(dig +short "$DOMAIN" | head -n1)
    SERVER_IP=$(curl -s ifconfig.me)
    
    if [ "$DOMAIN_IP" != "$SERVER_IP" ]; then
        print_warning "Domain $DOMAIN resolves to $DOMAIN_IP but this server has IP $SERVER_IP"
        print_warning "Make sure your domain points to this server before continuing"
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
    
    check_root
    install_certbot
    setup_webroot
    generate_certificate
    copy_certificates
    update_nginx_config
    setup_renewal
    
    print_status "✅ SSL certificate setup completed successfully!"
    print_status "Your certificates are located in: $SSL_DIR/live/$DOMAIN/"
    print_status "Automatic renewal is configured to run twice daily"
    print_status ""
    print_status "Next steps:"
    print_status "1. Start your containers: docker compose -f docker-compose.prod.yml up -d"
    print_status "2. Test your HTTPS site: https://$DOMAIN"
    print_status "3. Check certificate: https://www.ssllabs.com/ssltest/"
}

# Run main function
main "$@" 