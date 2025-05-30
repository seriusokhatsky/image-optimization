# File Optimization API Documentation

## Overview

This API provides asynchronous file optimization services with a task-based workflow. Upload files for optimization, track progress, and download results when ready.

## Base URL
```
http://your-domain.com/api
```

## Authentication
Currently no authentication required (add your auth requirements here if needed).

---

## Endpoints

### 1. Submit File for Optimization

**Endpoint:** `POST /optimize/submit`

**Description:** Upload a file for asynchronous optimization. Returns immediately with a task ID for tracking progress.

#### Request

**Headers:**
```
Content-Type: multipart/form-data
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `file` | File | Yes | Image file to optimize (max 10MB) |
| `quality` | Integer | No | Quality setting 1-100 (default: 80) |

**Supported File Types:**
- JPEG/JPG (optimized with MozJPEG)
- PNG (optimized with Pngquant + Optipng)
- WebP (optimized with Cwebp)
- GIF (optimized with Gifsicle)
- SVG (optimized with SVGO)

#### Response

**Success (202 Accepted):**
```json
{
  "success": true,
  "message": "File uploaded successfully. Optimization in progress.",
  "data": {
    "task_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "pending",
    "original_file": {
      "name": "my_photo.jpg",
      "size": 1024000
    },
    "estimated_completion": "2024-01-01T12:02:00.000000Z"
  }
}
```

**Error (422 Validation Error):**
```json
{
  "message": "The file field is required.",
  "errors": {
    "file": ["The file field is required."],
    "quality": ["The quality must be between 1 and 100."]
  }
}
```

#### cURL Example
```bash
curl -X POST http://your-domain.com/api/optimize/submit \
  -F "file=@/path/to/image.jpg" \
  -F "quality=80"
```

---

### 2. Check Task Status

**Endpoint:** `GET /optimize/status/{taskId}`

**Description:** Check the current status of an optimization task.

#### Request

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `taskId` | String | Yes | UUID of the optimization task |

#### Response

**Pending Status (200 OK):**
```json
{
  "success": true,
  "data": {
    "task_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "pending",
    "original_file": {
      "name": "my_photo.jpg",
      "size": 1024000
    },
    "created_at": "2024-01-01T12:00:00.000000Z"
  }
}
```

**Processing Status (200 OK):**
```json
{
  "success": true,
  "data": {
    "task_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "processing",
    "original_file": {
      "name": "my_photo.jpg",
      "size": 1024000
    },
    "created_at": "2024-01-01T12:00:00.000000Z",
    "started_at": "2024-01-01T12:00:30.000000Z"
  }
}
```

**Completed Status (200 OK):**
```json
{
  "success": true,
  "data": {
    "task_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "completed",
    "original_file": {
      "name": "my_photo.jpg",
      "size": 1024000
    },
    "optimization": {
      "compression_ratio": 25.5,
      "size_reduction": 261120,
      "algorithm": "JPEG optimization with MozJPEG (quality 80)",
      "processing_time": "234.56 ms",
      "optimized_size": 762880
    },
    "created_at": "2024-01-01T12:00:00.000000Z",
    "completed_at": "2024-01-01T12:01:15.000000Z",
    "download_url": "http://your-domain.com/api/optimize/download/550e8400-e29b-41d4-a716-446655440000"
  }
}
```

**Failed Status (200 OK):**
```json
{
  "success": true,
  "data": {
    "task_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "failed",
    "original_file": {
      "name": "corrupted.jpg",
      "size": 1024000
    },
    "error": "Optimization binary not found",
    "created_at": "2024-01-01T12:00:00.000000Z",
    "completed_at": "2024-01-01T12:00:45.000000Z"
  }
}
```

**Task Not Found (404 Not Found):**
```json
{
  "success": false,
  "message": "Task not found"
}
```

**Task Expired (410 Gone):**
```json
{
  "success": false,
  "message": "Task has expired"
}
```

#### cURL Example
```bash
curl -X GET http://your-domain.com/api/optimize/status/550e8400-e29b-41d4-a716-446655440000
```

---

### 3. Download Optimized File

**Endpoint:** `GET /optimize/download/{taskId}`

**Description:** Download the optimized file. File is automatically deleted after download.

#### Request

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `taskId` | String | Yes | UUID of the completed optimization task |

#### Response

**Success (200 OK):**
- Returns the optimized file as a download
- Filename format: `{original_name}-optimized.{extension}`
- Content-Disposition header sets the download filename
- File is automatically deleted after download

**Task Not Found (404 Not Found):**
```json
{
  "success": false,
  "message": "Task not found or not completed"
}
```

**Task Expired (410 Gone):**
```json
{
  "success": false,
  "message": "Task has expired"
}
```

**File Not Found (404 Not Found):**
```json
{
  "success": false,
  "message": "Optimized file not found"
}
```

#### cURL Example
```bash
curl -X GET http://your-domain.com/api/optimize/download/550e8400-e29b-41d4-a716-446655440000 \
  -o "optimized_file.jpg"
```

---

## Workflow Examples

### Basic Optimization Workflow

1. **Submit file for optimization:**
```bash
curl -X POST http://your-domain.com/api/optimize/submit \
  -F "file=@my_photo.jpg" \
  -F "quality=85"
```

2. **Poll for completion:**
```bash
# Check status every few seconds
curl -X GET http://your-domain.com/api/optimize/status/550e8400-e29b-41d4-a716-446655440000
```

3. **Download when completed:**
```bash
curl -X GET http://your-domain.com/api/optimize/download/550e8400-e29b-41d4-a716-446655440000 \
  -o "my_photo-optimized.jpg"
```

### JavaScript Example

```javascript
async function optimizeFile(file, quality = 80) {
  // Step 1: Submit file
  const formData = new FormData();
  formData.append('file', file);
  formData.append('quality', quality);
  
  const submitResponse = await fetch('/api/optimize/submit', {
    method: 'POST',
    body: formData
  });
  
  const submitResult = await submitResponse.json();
  const taskId = submitResult.data.task_id;
  
  // Step 2: Poll for completion
  let status = 'pending';
  while (status === 'pending' || status === 'processing') {
    await new Promise(resolve => setTimeout(resolve, 2000)); // Wait 2 seconds
    
    const statusResponse = await fetch(`/api/optimize/status/${taskId}`);
    const statusResult = await statusResponse.json();
    status = statusResult.data.status;
    
    if (status === 'failed') {
      throw new Error(statusResult.data.error);
    }
  }
  
  // Step 3: Download optimized file
  if (status === 'completed') {
    const downloadUrl = `/api/optimize/download/${taskId}`;
    window.open(downloadUrl, '_blank');
  }
}

// Usage
document.getElementById('fileInput').addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (file) {
    try {
      await optimizeFile(file, 80);
      console.log('File optimized and downloaded successfully!');
    } catch (error) {
      console.error('Optimization failed:', error.message);
    }
  }
});
```

---

## Status Codes

| Code | Description |
|------|-------------|
| 200 | OK - Request successful |
| 202 | Accepted - File submitted for processing |
| 404 | Not Found - Task not found or file not found |
| 410 | Gone - Task expired or endpoint deprecated |
| 422 | Unprocessable Entity - Validation errors |
| 500 | Internal Server Error - Server error |

---

## Task Lifecycle

1. **Created** - Task created, file uploaded
2. **Pending** - Waiting in queue for processing
3. **Processing** - Being optimized
4. **Completed** - Optimization successful, ready for download
5. **Failed** - Optimization failed
6. **Expired** - Task expired (24 hours after creation)

---

## Rate Limits

- File size limit: 10MB
- Supported formats: JPEG, PNG, WebP, GIF, SVG
- Task expiration: 24 hours
- No rate limits currently implemented

---

## Error Handling

All errors follow a consistent format:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Specific field error"]
  }
}
```

Common error scenarios:
- File too large (>10MB)
- Unsupported file format
- Task not found
- Task expired
- Optimization binary missing
- File corruption

---

## File Naming Convention

**Downloaded files use the format:**
- Original: `my_photo.jpg` → Download: `my_photo-optimized.jpg`
- Original: `image-100x50.png` → Download: `image-100x50-optimized.png`

**Internal storage uses UUID naming for uniqueness.**

---

## Logging

All optimization tasks are logged to `storage/logs/optimization-YYYY-MM-DD.log` with detailed information including:

- Task creation and completion events
- Success/failure results
- Performance metrics
- Error details and stack traces
- Download events

---

## Queue Configuration

The API uses Laravel queues for asynchronous processing. Make sure to run:

```bash
php artisan queue:work
```

For production, use a process manager like Supervisor to ensure queue workers stay running.

---

## Cleanup

- Files are automatically deleted after download
- Expired tasks are cleaned up hourly via scheduled command
- Original files are deleted when optimization completes or task is downloaded 