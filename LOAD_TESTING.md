# Load Testing Guide for Image Optimization API

This guide will help you test your production server's capacity to handle concurrent image optimization requests.

## Quick Start

### 1. Install k6 (Load Testing Tool)

```bash
# macOS
brew install k6

# Linux
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6
```

### 2. Basic Load Testing

```bash
# Test API responsiveness (quick test)
k6 run --env BASE_URL=https://your-production-domain.com load-test-simple.js

# Full optimization workflow test (comprehensive)
k6 run --env BASE_URL=https://your-production-domain.com load-test.js
```

### 3. Monitor Server Performance

```bash
# Start monitoring (in a separate terminal)
./load-test-monitor.sh your-production-domain.com
```

## Test Scripts Included

### 1. `load-test-simple.js`
- **Purpose**: Quick API responsiveness test
- **Tests**: Health endpoint, status endpoint response times
- **Duration**: ~2 minutes
- **Max Concurrent Users**: 100
- **Use When**: Quick health check, testing basic server capacity

### 2. `load-test.js`
- **Purpose**: Full optimization workflow test
- **Tests**: File upload (~600KB images), optimization processing, download
- **Duration**: ~6 minutes
- **Max Concurrent Users**: 30 (configurable)
- **Use When**: Testing realistic user scenarios with actual image processing

### 3. `load-test-monitor.sh`
- **Purpose**: Server resource monitoring during tests
- **Monitors**: CPU, memory, Docker stats, API response times, queue status
- **Logs**: Saves to timestamped files for analysis

## Understanding Results

### Key Metrics to Watch

1. **Response Times**
   - `http_req_duration`: How long API calls take
   - Target: p95 < 2-5 seconds for most endpoints

2. **Error Rates**
   - `http_req_failed`: Percentage of failed requests
   - Target: < 5-10% error rate

3. **Optimization Success**
   - `optimization_success`: Percentage of successful optimizations
   - Target: > 80% success rate

4. **Optimization Duration**
   - `optimization_duration`: Time to complete image processing
   - Target: p95 < 30 seconds

### Sample Good Results
```
✓ http_req_duration..........: avg=1.2s   min=200ms med=1.1s   max=4.8s   p(90)=2.1s   p(95)=3.2s
✓ http_req_failed............: 2.5%  ✓ 0 ✗ 45
✓ optimization_success.......: 92.3% ✓ 120 ✗ 10
✓ optimization_duration......: avg=8.5s  min=2.1s  med=7.8s  max=25.1s  p(95)=18.2s
```

### Warning Signs
- Response times > 5 seconds consistently
- Error rates > 10%
- Optimization success < 80%
- Processing times > 60 seconds

## Customizing Tests

### Adjust Concurrent Users

Edit the `stages` in load test files:

```javascript
stages: [
  { duration: '30s', target: 5 },   // Start with 5 users
  { duration: '1m', target: 20 },   // Increase to 20 users
  { duration: '30s', target: 50 },  // Test with 50 users
  { duration: '1m', target: 50 },   // Hold at 50 users
  { duration: '30s', target: 0 },   // Ramp down
],
```

### Change Test Duration

Modify `duration` values to test longer:

```javascript
{ duration: '5m', target: 30 },   // Test 30 users for 5 minutes
```

### Adjust Thresholds

Modify acceptable performance levels:

```javascript
thresholds: {
  http_req_duration: ['p(95)<10000'], // Allow 10s response time
  http_req_failed: ['rate<0.15'],     // Allow 15% error rate
  optimization_success: ['rate>0.7'], // Require 70% success rate
},
```

## Server Monitoring

### What to Monitor

1. **CPU Usage**: Should stay below 80-90%
2. **Memory Usage**: Watch for memory leaks
3. **Disk I/O**: Image processing is disk-intensive
4. **Network**: Upload/download bandwidth
5. **Queue Length**: How many jobs are pending

### Queue Performance

Your API uses Laravel queues. Monitor queue status:

```bash
# On your server
docker exec optimizer-app php artisan queue:work --timeout=300 --sleep=3 --tries=3 --max-jobs=100

# Check queue status
docker exec optimizer-app php artisan queue:monitor
```

## Finding Your Limits

### Step-by-Step Capacity Testing

1. **Start Small**: Begin with 5 concurrent users
2. **Gradual Increase**: Double users every test run
3. **Watch Metrics**: Monitor response times and error rates
4. **Find Breaking Point**: Note when performance degrades
5. **Determine Safe Capacity**: Use 70% of breaking point as safe limit

### Example Capacity Discovery

```bash
# Test 1: 10 users
k6 run --env BASE_URL=https://your-domain.com load-test.js

# Test 2: 20 users (edit load-test.js stages)
k6 run --env BASE_URL=https://your-domain.com load-test.js

# Test 3: 40 users
k6 run --env BASE_URL=https://your-domain.com load-test.js

# Continue until you see degradation
```

## Production Recommendations

### Queue Workers
Since your production uses [Supervisor for queue management][[memory:5971310225779170498]], ensure adequate workers:

```bash
# Check current workers
supervisorctl status

# Restart if needed
supervisorctl restart all
```

### Database Optimization

Monitor MySQL during tests:

```sql
SHOW PROCESSLIST;
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Queries_per_second_avg';
```

### Expected Capacity

For a typical single-server setup:
- **Light Load**: 10-20 concurrent optimizations
- **Medium Load**: 20-50 concurrent optimizations  
- **Heavy Load**: 50+ concurrent optimizations (depends on server specs)

Image processing is CPU and I/O intensive, so capacity depends heavily on:
- Server CPU cores
- Available RAM
- Disk speed (SSD recommended)
- Network bandwidth

## Troubleshooting

### Common Issues

1. **Timeouts**: Increase timeout values in load tests
2. **Memory Errors**: Check server RAM, optimize image processing
3. **Queue Failures**: Verify queue workers are running
4. **Database Locks**: Monitor MySQL for deadlocks
5. **File System**: Ensure sufficient disk space for temp files

### Performance Tuning

1. **Increase PHP Memory**: `memory_limit = 512M`
2. **Optimize Queue Workers**: More parallel workers
3. **Database Tuning**: Increase connection pool
4. **File Cleanup**: Ensure temp files are cleaned up
5. **Caching**: Implement Redis for better performance

Run these tests gradually and monitor your server closely to avoid overwhelming your production environment. 