# Manual Deployment Instructions

This guide provides the minimal commands needed to perform a first-time deployment and subsequent updates manually.

---

## 1. First-Time Deployment

Follow these steps to deploy the application to a new server for the first time. This is based on the `deploy-first.sh` script.

### Prerequisites (On Your Local Machine)

- Ensure you have your SSL certificate files (`fullchain.pem`, `privkey.pem`) in a local `certs/` directory.
- Your SSH key must be configured for passwordless access to the server.

### Step 1: Copy SSL Certificates (Run Locally)

First, upload your SSL certificates from your local machine to the server.

```bash
# Replace with your server IP and domain
SERVER_IP="157.180.83.204"
CERT_DIR="/etc/nginx/ssl/live/img-optim.xtemos.com"

# Create remote directory via SSH
ssh root@$SERVER_IP "mkdir -p $CERT_DIR"

# Copy certificate files
scp ./certs/fullchain.pem root@$SERVER_IP:$CERT_DIR/local-cert.pem
scp ./certs/privkey.pem root@$SERVER_IP:$CERT_DIR/local-key.pem

# Set correct permissions on the server
ssh root@$SERVER_IP "chmod 644 $CERT_DIR/local-cert.pem && chmod 600 $CERT_DIR/local-key.pem && chown root:root $CERT_DIR/*"
```

### Step 2: Server Setup & Application Deployment (Run on the Server)

Connect to your server, then run the following commands to set up the application.

```bash
# Connect to your server
ssh root@157.180.83.204

# --- Once connected to the server, run these commands ---

# 1. Clone the repository
mkdir -p /var/www
cd /var/www
git clone https://github.com/seriusokhatsky/image-optimization.git optimizer
cd optimizer

# 2. Create and configure the .env file
# We will use the example file as a template.
cp .env.example .env

# 3. Generate and save the Application Key
# This command replaces the empty APP_KEY in your .env file.
APP_KEY="base64:$(openssl rand -base64 32)"
sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env

# 4. Manually edit the .env file
# IMPORTANT: You must set your database password and other sensitive values.
nano .env

# 5. Build and start the containers
docker build --target production -t optimizer-app:production -f docker/production/Dockerfile .
docker compose -f docker-compose.prod.yml up -d

# 6. Run database migrations and optimizations
# Wait a few seconds for the app container to be ready.
sleep 10
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache

# 7. Check the status of your containers
docker compose -f docker-compose.prod.yml ps
```

---

## 2. Updating an Existing Deployment

Use these steps to update an already-deployed application with the latest code. This is based on the `deploy-simple.sh` script.

### Step 1: Push Your Changes (Run Locally)

Make sure your latest code is pushed to the git repository.

```bash
git push origin main
```

### Step 2: Deploy on the Server (Run on the Server)

Connect to your server and run the following commands to deploy the update.

```bash
# Connect to your server
ssh root@157.180.83.204

# --- Once connected to the server, run these commands ---

# 1. Navigate to the project directory
cd /var/www/optimizer

# 2. Pull the latest code from the main branch
git pull origin main

# 3. Rebuild the application image with the latest code
docker build --target production -t optimizer-app:production -f docker/production/Dockerfile .

# 4. Deploy the new container
# Docker compose will gracefully stop the old container and start the new one.
docker compose -f docker-compose.prod.yml up -d --no-deps app

# 5. Run database migrations and clear caches
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache

# 6. Check the status of your containers
docker compose -f docker-compose.prod.yml ps
``` 