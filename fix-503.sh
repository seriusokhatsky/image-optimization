#!/bin/bash

# Quick 503 error fix script
echo "🔧 Quick fix for 503 errors..."

ssh root@157.180.83.204 << 'ENDSSH'
    cd /var/www/optimizer
    
    echo "🔄 Removing maintenance mode and bringing app online..."
    docker compose -f docker-compose.prod.yml exec -T app rm -f /var/www/html/storage/framework/down || true
    docker compose -f docker-compose.prod.yml exec -T app php artisan up
    
    echo "🧹 Clearing caches..."
    docker compose -f docker-compose.prod.yml exec -T app php artisan cache:clear || true
    docker compose -f docker-compose.prod.yml exec -T app php artisan view:clear || true
    
    echo "🔍 Testing..."
    sleep 3
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://img-optim.xtemos.com/)
    if [ "$HTTP_CODE" = "200" ]; then
        echo "✅ Fixed! Application responding (HTTP $HTTP_CODE)"
    else
        echo "⚠️ Still getting HTTP $HTTP_CODE"
    fi
ENDSSH

echo "🌐 Check: https://img-optim.xtemos.com" 