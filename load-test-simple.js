import http from 'k6/http';
import { check } from 'k6';

// Simple load test - just tests API responsiveness
export let options = {
  scenarios: {
    quick_load: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: [
        { duration: '10s', target: 10 },
        { duration: '30s', target: 50 },
        { duration: '10s', target: 100 },
        { duration: '30s', target: 100 },
        { duration: '10s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<2000'],
    http_req_failed: ['rate<0.05'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost';

export default function () {
  // Test health endpoint
  const healthResponse = http.get(`${BASE_URL}/health`);
  
  check(healthResponse, {
    'health check status 200': (r) => r.status === 200,
    'health check response time < 500ms': (r) => r.timings.duration < 500,
  });

  // Test status endpoint with random task ID (should return 404)
  const randomTaskId = Math.random().toString(36).substring(7);
  const statusResponse = http.get(`${BASE_URL}/api/optimize/status/${randomTaskId}`);
  
  check(statusResponse, {
    'status endpoint responsive': (r) => r.status === 404, // Expected for random ID
    'status response time < 1000ms': (r) => r.timings.duration < 1000,
  });
} 