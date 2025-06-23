import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// Custom metrics
const optimizationSuccess = new Rate('optimization_success');
const optimizationDuration = new Trend('optimization_duration'); // Just processing time
const downloadSuccess = new Rate('download_success');
const endToEndDuration = new Trend('end_to_end_duration'); // Complete workflow time

// Test configuration - adjust these based on your server capacity
export let options = {
  scenarios: {
    // Ramp up gradually to find the breaking point
    load_test: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: [
        { duration: '30s', target: 5 },   // Ramp up to 5 users
        { duration: '1m', target: 10 },   // Stay at 10 users
        { duration: '30s', target: 20 },  // Ramp up to 20 users
        { duration: '1m', target: 20 },   // Stay at 20 users
        { duration: '30s', target: 30 },  // Ramp up to 30 users
        { duration: '1m', target: 30 },   // Stay at 30 users
        { duration: '30s', target: 0 },   // Ramp down
      ],
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<5000'], // 95% of requests should be under 5s
    http_req_failed: ['rate<0.1'],     // Error rate should be less than 10%
    optimization_success: ['rate>0.8'], // 80% optimization success rate
    optimization_duration: ['p(95)<30000'], // 95% optimizations under 30s
    end_to_end_duration: ['p(95)<45000'], // 95% end-to-end under 45s
  },
};

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const API_BASE = `${BASE_URL}/api`;

// Generate a test JPEG-like image data (similar size to your test-image.jpg: ~136KB)
function generateTestImageData() {
  // Create a buffer that simulates a JPEG file structure
  const size = 1000 * 1024; // 8800KB like your test image
  const buffer = new ArrayBuffer(size);
  const view = new Uint8Array(buffer);
  
  // Add JPEG header (basic)
  view[0] = 0xFF;
  view[1] = 0xD8;
  view[2] = 0xFF;
  view[3] = 0xE0;
  
  // Fill with random data that looks like compressed image data
  for (let i = 4; i < size - 2; i++) {
    view[i] = Math.floor(Math.random() * 256);
  }
  
  // Add JPEG footer
  view[size - 2] = 0xFF;
  view[size - 1] = 0xD9;
  
  return buffer;
}

const TEST_IMAGE_DATA = generateTestImageData();

export default function () {
  const testId = Math.random().toString(36).substring(7);
  
  // Step 1: Submit optimization task
  const submitPayload = {
    file: http.file(TEST_IMAGE_DATA, `test-${testId}.jpg`, 'image/jpeg'),
    quality: Math.floor(Math.random() * 40) + 60, // Random quality 60-100
    generate_webp: 1, // Random boolean
  };

  const submitResponse = http.post(`${API_BASE}/optimize/submit`, submitPayload, {
    headers: { 'Accept': 'application/json' },
  });

  const submitSuccess = check(submitResponse, {
    'submission status is 202': (r) => r.status === 202,
    'submission returns task_id': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.data && body.data.task_id;
      } catch {
        return false;
      }
    },
  });

  if (!submitSuccess) {
    console.log(`Submission failed: ${submitResponse.status} ${submitResponse.body}`);
    return;
  }

  const taskId = JSON.parse(submitResponse.body).data.task_id;
  const startTime = Date.now(); // Start timing the complete workflow
  const processingStartTime = Date.now(); // Also track just processing time

  // Step 2: Poll for completion
  let attempts = 0;
  const maxAttempts = 60; // Max 5 minutes of polling (5s intervals)
  let optimizationCompleted = false;

  while (attempts < maxAttempts && !optimizationCompleted) {
    sleep(0.5); // Wait 1 seconds between polls
    attempts++;

    const statusResponse = http.get(`${API_BASE}/optimize/status/${taskId}`, {
      headers: { 'Accept': 'application/json' },
    });

    const statusCheck = check(statusResponse, {
      'status check successful': (r) => r.status === 200,
    });

    if (!statusCheck) {
      console.log(`Status check failed: ${statusResponse.status}`);
      break;
    }

    const statusData = JSON.parse(statusResponse.body);
    const status = statusData.data.status;

    if (status === 'completed') {
      optimizationCompleted = true;
      const processingDuration = Date.now() - processingStartTime;
      optimizationDuration.add(processingDuration);
      optimizationSuccess.add(1);

      // Step 3: Test download - WebP first (if available), then main image
      let webpDownloadSuccess = true;
      
      // Test WebP download first if available (before main download deletes files)
      if (statusData.data.webp_download_url) {
        const webpResponse = http.get(`${API_BASE}/optimize/download/${taskId}/webp`);
        webpDownloadSuccess = check(webpResponse, {
          'webp download successful': (r) => r.status === 200,
          'webp download has content': (r) => r.body.length > 0,
        });
      }

      // Then download main image (this will delete task and cleanup files)
      const downloadResponse = http.get(`${API_BASE}/optimize/download/${taskId}`);
      
      const downloadCheck = check(downloadResponse, {
        'download successful': (r) => r.status === 200,
        'download has content': (r) => r.body.length > 0,
      });

      downloadSuccess.add(downloadCheck ? 1 : 0);

      // Measure complete end-to-end time (upload → completed + downloaded)
      const endToEndTime = Date.now() - startTime;
      endToEndDuration.add(endToEndTime);

      console.log(`Task ${taskId} - Processing: ${processingDuration}ms, End-to-end: ${endToEndTime}ms`);
      
    } else if (status === 'failed') {
      optimizationSuccess.add(0);
      console.log(`Task ${taskId} failed: ${statusData.data.error || 'Unknown error'}`);
      break;
    } else if (status === 'processing' || status === 'pending') {
      // Continue polling
      console.log(`Task ${taskId} status: ${status} (attempt ${attempts})`);
    }
  }

  if (!optimizationCompleted && attempts >= maxAttempts) {
    optimizationSuccess.add(0);
    console.log(`Task ${taskId} timed out after ${maxAttempts} attempts`);
  }
} 