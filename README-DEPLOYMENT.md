# Deployment Guide - Hetzner Cloud (Production)

This guide will help you deploy your Laravel Optimizer application to a Hetzner Cloud server using GitHub and a production-optimized Docker setup.

## Production Optimizations

The production Docker setup includes:

✅ **No Xdebug** - Removed for better performance
✅ **Optimized PHP settings** - OPcache enabled, security hardened
✅ **Production Composer** - `--no-dev` flag excludes development packages
✅ **Asset compilation** - Frontend assets built during image creation
✅ **Security hardening** - Non-root user, secure PHP configuration
✅ **Smaller image size** - .dockerignore excludes unnecessary files
✅ **Laravel optimizations** - Config, routes, and views cached
✅ **Complete image optimization** - All image processing tools including MozJPEG

## Prerequisites

1. A Hetzner Cloud server (the simplest one is sufficient)
2. Your Laravel project on GitHub (public or private repository)
3. SSH access to your server
4. Your domain pointed to the server's IP address (optional, can use IP initially)

## Quick Deployment Steps

### 1. Configure Deployment Script

Edit the `deploy.sh` file and update these variables:

```bash
REMOTE_HOST="YOUR_HETZNER_IP"           # Your server's IP address
REPO_URL="YOUR_GITHUB_REPO_URL"        # Your GitHub repository URL
```

**Example:**
```bash
REMOTE_HOST="116.203.123.45"
REPO_URL="https://github.com/yourusername/optimizer.git"
```

### 2. Run Deployment

From your local project directory:

```bash
# Make the script executable (first time only)
chmod +x deploy.sh

# Deploy from main branch
./deploy.sh

# Or deploy from a specific branch
./deploy.sh develop
```

The script will:
- 🔄 Push your latest changes to GitHub (optional)
- 🔧 Install Docker and dependencies on your server
- 📦 Clone/update your repository on the server
- 🏗️ Build and start containers
- 🎯 Run Laravel optimizations and migrations
- ✅ Complete the deployment automatically

### 3. Configure Environment

SSH to your server and edit the `.env` file:

```bash
ssh root@YOUR_HETZNER_IP
cd /var/www/optimizer
nano .env
```

Update these important settings:
```bash
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_GENERATED_KEY_HERE
APP_URL=https://your-domain.com

DB_PASSWORD=your-secure-password
# ... other settings
```

### 4. Restart Containers

After editing `.env`:

```bash
docker-compose -f docker-compose.prod.yml restart
```

## Alternative: Manual GitHub Deployment

If you prefer manual deployment:

### 1. SSH to Your Server
```bash
ssh root@YOUR_HETZNER_IP
```

### 2. Install Dependencies
```bash
# Install Git and Docker
apt update
apt install -y git
curl -fsSL https://get.docker.com | sh
systemctl enable docker && systemctl start docker

# Install Docker Compose
curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose
```

### 3. Clone Repository
```bash
# Clone your repository
git clone https://github.com/yourusername/optimizer.git /var/www/optimizer
cd /var/www/optimizer

# Create .env file
cp .env.example .env
nano .env  # Edit with your production settings
```

### 4. Deploy
```bash
# Build and start containers
docker-compose -f docker-compose.prod.yml up -d --build

# Generate app key and run migrations
docker-compose -f docker-compose.prod.yml exec app php artisan key:generate
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

## Files Created for Production

- `docker/production/Dockerfile` - Production-optimized Docker image
- `docker/production/php.ini` - Production PHP configuration  
- `docker/production/supervisord.conf` - Production supervisor configuration
- `docker/production/start-container` - Production startup script
- `docker-compose.prod.yml` - Production Docker Compose configuration
- `docker/nginx/nginx.conf` - Nginx configuration for reverse proxy and SSL
- `.dockerignore` - Excludes development files from production image
- `deploy.sh` - **Updated GitHub-based deployment script**

## Production Image Features

### PHP Optimizations
- **OPcache enabled** with production settings
- **Realpath cache** for better file system performance
- **Security hardened** - `expose_php=Off`, error display disabled
- **Memory optimized** - 512M limit with efficient garbage collection

### Security Features
- **Non-root execution** - Application runs as `www-data` user
- **Secure session settings** - HTTPOnly, Secure, SameSite cookies
- **Production error handling** - Errors logged, not displayed
- **File permissions** - Proper Laravel directory permissions

### Performance Features
- **Composer optimizations** - `--optimize-autoloader --no-dev`
- **Laravel caching** - Config, routes, views pre-cached
- **Asset compilation** - Frontend assets built during image creation
- **Smaller image size** - Development dependencies excluded

### Image Optimization Features
- **Complete toolset** - jpegoptim, optipng, pngquant, gifsicle
- **Modern formats** - WebP and AVIF support
- **MozJPEG** - Superior JPEG compression (10% better than standard)
- **Video processing** - FFmpeg for thumbnails and conversions

## Continuous Deployment

### GitHub Actions (Optional)

Create `.github/workflows/deploy.yml` for automatic deployment:

```yaml
name: Deploy to Hetzner

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Deploy to server
        uses: appleboy/ssh-action@v0.1.5
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: root
          key: ${{ secrets.SERVER_SSH_KEY }}
          script: |
            cd /var/www/optimizer
            git pull origin main
            docker-compose -f docker-compose.prod.yml up -d --build
            docker-compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
            docker-compose -f docker-compose.prod.yml exec -T app php artisan optimize
```

### Quick Updates

For quick updates after initial deployment:

```bash
# On your local machine
git add .
git commit -m "Update feature"
git push origin main

# Run deployment script
./deploy.sh
```

## SSL Certificate Setup

### Option 1: Let's Encrypt (Recommended)

```bash
# SSH to your server
ssh root@YOUR_HETZNER_IP
cd /var/www/optimizer

# Install Certbot
apt install certbot

# Stop nginx temporarily
docker-compose -f docker-compose.prod.yml stop nginx

# Generate certificate
certbot certonly --standalone -d your-domain.com

# Copy certificates
mkdir -p docker/nginx/ssl
cp /etc/letsencrypt/live/your-domain.com/fullchain.pem docker/nginx/ssl/cert.pem
cp /etc/letsencrypt/live/your-domain.com/privkey.pem docker/nginx/ssl/key.pem

# Update nginx config with your domain
sed -i 's/your-domain.com/actual-domain.com/g' docker/nginx/nginx.conf

# Restart containers
docker-compose -f docker-compose.prod.yml up -d
```

### Option 2: Self-Signed Certificate (For Testing)

```bash
mkdir -p docker/nginx/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout docker/nginx/ssl/key.pem \
    -out docker/nginx/ssl/cert.pem \
    -subj "/C=US/ST=State/L=City/O=Organization/CN=your-domain.com"
```

## Monitoring and Maintenance

### View Logs
```bash
# View all container logs
docker-compose -f docker-compose.prod.yml logs -f

# View specific container logs
docker-compose -f docker-compose.prod.yml logs app
docker-compose -f docker-compose.prod.yml logs mysql
docker-compose -f docker-compose.prod.yml logs nginx
```

### Update Application
```bash
# Using the deployment script (recommended)
./deploy.sh

# Or manually on server
ssh root@YOUR_HETZNER_IP
cd /var/www/optimizer
git pull origin main
docker-compose -f docker-compose.prod.yml up -d --build
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker-compose -f docker-compose.prod.yml exec app php artisan optimize
```

### Backup Database
```bash
docker-compose -f docker-compose.prod.yml exec mysql mysqldump -u sail -p optimizer > backup_$(date +%Y%m%d_%H%M%S).sql
```

## Deployment Workflow Comparison

| Method | Pros | Cons |
|--------|------|------|
| **GitHub + Script** | ✅ Automated<br>✅ Version controlled<br>✅ Easy rollbacks | ⚠️ Requires Git setup |
| **GitHub Actions** | ✅ Fully automated<br>✅ CI/CD pipeline | ⚠️ More complex setup |
| **Manual rsync** | ✅ Simple<br>✅ No Git required | ❌ No version control<br>❌ Manual process |

## Troubleshooting

### Git Issues
```bash
# If git authentication fails
ssh-keygen -t rsa -b 4096 -C "your-email@example.com"
# Add the public key to your GitHub account

# For private repositories, use SSH URL
REPO_URL="git@github.com:yourusername/optimizer.git"
```

### Container Issues
```bash
# Check container status
docker ps -a

# Rebuild from scratch
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml up -d --build --force-recreate
```

## Security Recommendations

1. **Use SSH Keys** for GitHub authentication
2. **Set up firewall** (UFW) on your server
3. **Use strong passwords** for database and applications
4. **Keep server updated** regularly
5. **Monitor logs** for suspicious activity

Your application will be available at `https://your-domain.com` once configured properly! 