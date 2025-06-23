#!/bin/bash

# Server monitoring script for load testing
# Run this alongside your load tests to monitor server performance

PRODUCTION_SERVER=${1:-"your-production-server.com"}
LOG_FILE="load-test-$(date +%Y%m%d-%H%M%S).log"

echo "Starting load test monitoring for $PRODUCTION_SERVER"
echo "Log file: $LOG_FILE"
echo "=========================================="

# Function to log with timestamp
log_with_timestamp() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Monitor server resources via SSH (if you have access)
monitor_server_resources() {
    if command -v ssh &> /dev/null; then
        log_with_timestamp "=== Server Resources ==="
        
        # CPU and Memory usage
        ssh "$PRODUCTION_SERVER" "top -bn1 | head -20" 2>/dev/null | tee -a "$LOG_FILE" || \
            log_with_timestamp "Cannot SSH to server - monitoring from client side only"
        
        # Docker container stats
        ssh "$PRODUCTION_SERVER" "docker stats --no-stream" 2>/dev/null | tee -a "$LOG_FILE" || \
            log_with_timestamp "Cannot get Docker stats"
            
        # Disk space
        ssh "$PRODUCTION_SERVER" "df -h" 2>/dev/null | tee -a "$LOG_FILE"
        
        echo "---" | tee -a "$LOG_FILE"
    fi
}

# Monitor API health and response times
monitor_api_health() {
    log_with_timestamp "=== API Health Check ==="
    
    # Health endpoint
    HEALTH_RESPONSE=$(curl -s -w "Time: %{time_total}s | Status: %{http_code}" \
        "http://$PRODUCTION_SERVER/health" 2>/dev/null)
    log_with_timestamp "Health: $HEALTH_RESPONSE"
    
    # Test status endpoint response time
    TASK_ID="test-$(date +%s)"
    STATUS_RESPONSE=$(curl -s -w "Time: %{time_total}s | Status: %{http_code}" \
        "http://$PRODUCTION_SERVER/api/optimize/status/$TASK_ID" 2>/dev/null)
    log_with_timestamp "Status: $STATUS_RESPONSE"
    
    echo "---" | tee -a "$LOG_FILE"
}

# Monitor MySQL (if accessible)
monitor_database() {
    log_with_timestamp "=== Database Performance ==="
    
    # Check if we can monitor MySQL
    if command -v ssh &> /dev/null; then
        ssh "$PRODUCTION_SERVER" "docker exec optimizer-mysql mysql -u root -p\$DB_PASSWORD -e 'SHOW PROCESSLIST; SHOW STATUS LIKE \"Threads_connected\";'" 2>/dev/null | tee -a "$LOG_FILE" || \
            log_with_timestamp "Cannot access MySQL - check credentials"
    fi
    
    echo "---" | tee -a "$LOG_FILE"
}

# Monitor queue performance
monitor_queue() {
    log_with_timestamp "=== Queue Status ==="
    
    # If we can access the server, check queue status
    if command -v ssh &> /dev/null; then
        ssh "$PRODUCTION_SERVER" "docker exec optimizer-app php artisan queue:monitor" 2>/dev/null | tee -a "$LOG_FILE" || \
            log_with_timestamp "Cannot check queue status"
    fi
    
    echo "---" | tee -a "$LOG_FILE"
}

# Main monitoring loop
echo "Starting monitoring (Ctrl+C to stop)..."
echo "Monitoring server: $PRODUCTION_SERVER"

while true; do
    log_with_timestamp "====== MONITORING CYCLE ======"
    
    monitor_api_health
    monitor_server_resources
    monitor_database
    monitor_queue
    
    log_with_timestamp "====== END CYCLE ======"
    echo ""
    
    sleep 30  # Monitor every 30 seconds
done 