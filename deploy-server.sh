#!/bin/bash
set -e

echo "🚀 Starting server-side deployment..."

cd /var/www/optimizer

# Get new image ID
NEW_IMAGE=$(docker images optimizer-app:production --format "{{.ID}}")
echo "📋 New image to deploy: $NEW_IMAGE"

# Stop and recreate container
echo "🔄 Recreating container..."
docker compose -f docker-compose.prod.yml stop app || true
docker compose -f docker-compose.prod.yml rm -f app || true
docker compose -f docker-compose.prod.yml up -d --no-deps app

# Wait for container
echo "⏳ Waiting for container..."
sleep 10

# Verify new image is running
RUNNING_IMAGE=$(docker compose -f docker-compose.prod.yml ps app --format "{{.Image}}" | head -1)
if [[ "$RUNNING_IMAGE" == *"$NEW_IMAGE"* ]]; then
    echo "✅ Container using new image: $NEW_IMAGE"
else
    echo "❌ Container using wrong image: $RUNNING_IMAGE (expected: $NEW_IMAGE)"
    echo "🔄 Forcing recreation..."
    docker compose -f docker-compose.prod.yml stop app || true
    docker compose -f docker-compose.prod.yml rm -f app || true
    docker compose -f docker-compose.prod.yml up -d --no-deps app
    sleep 10
fi

# Verify latest changes
echo "🔍 Verifying changes..."
if docker compose -f docker-compose.prod.yml exec -T app grep -q "Bulletproof16 Deployments" resources/views/demo.blade.php 2>/dev/null; then
    echo "✅ Latest changes confirmed!"
else
    echo "⚠️ Changes not found, checking current content..."
    docker compose -f docker-compose.prod.yml exec -T app grep -o "Lightning Fast HOO.*Deployments" resources/views/demo.blade.php || echo "Text not found"
fi

# Bring app online
echo "🟢 Bringing application online..."
docker compose -f docker-compose.prod.yml exec -T app rm -f /var/www/html/storage/framework/down || true
docker compose -f docker-compose.prod.yml exec -T app php artisan up || true

# Run maintenance tasks
echo "🧹 Running maintenance..."
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force || echo "Migration failed"
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear || true
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache || true
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache || true
docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache || true

echo "✅ Server-side deployment completed!" 