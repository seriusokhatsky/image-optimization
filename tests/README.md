# Test Suite Documentation

This test suite provides comprehensive coverage for the Laravel Image Optimization API application.

## Test Structure

### Feature Tests
- **OptimizeControllerTest.php** - Tests all API endpoints for image optimization
- **HomeControllerTest.php** - Tests demo interface and web routes
- **DatabaseTest.php** - Database integration and migration tests
- **ExampleTest.php** - Application health checks

### Unit Tests
- **OptimizationTaskTest.php** - Model logic and relationships
- **FormatHelperTest.php** - Utility functions
- **FileOptimizationServiceTest.php** - Core optimization service
- **OptimizeFileJobTest.php** - Queue job processing
- **ImageUploadRateLimitTest.php** - Middleware functionality

## Test Coverage

### Controllers
- ✅ OptimizeController (submit, status, download, downloadWebp)
- ✅ HomeController (index, upload, status)

### Models
- ✅ OptimizationTask (creation, status management, WebP handling, expiration)
- ✅ User (basic functionality)

### Services
- ✅ FileOptimizationService (optimization logic, file type support)
- ✅ WebpConverterService (through integration tests)
- ✅ OptimizationLogger (through job tests)

### Jobs
- ✅ OptimizeFileJob (execution, failure handling, WebP generation)

### Middleware
- ✅ ImageUploadRateLimit (rate limiting logic)

### Helpers
- ✅ FormatHelper (byte formatting)

### Database
- ✅ Migrations (table structure)
- ✅ Constraints and relationships
- ✅ Query scopes

## Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/OptimizeControllerTest.php

# Run with coverage
./vendor/bin/pest --coverage

# Run specific test group
./vendor/bin/pest --filter="OptimizeController API"
```

## Test Environment

Tests use:
- SQLite in-memory database
- Fake storage disk
- Mock queue connections
- Fake cache

## Key Testing Patterns

1. **Factory Usage** - OptimizationTaskFactory with states
2. **Mocking** - Services and external dependencies
3. **Storage Faking** - File upload/download simulation  
4. **Queue Faking** - Job dispatch verification
5. **Database Transactions** - Clean test isolation

## API Test Coverage

### Submit Endpoint (`POST /api/optimize/submit`)
- File upload validation
- Quality parameter validation
- Task creation
- Job dispatch
- Response format

### Status Endpoint (`GET /api/optimize/status/{taskId}`)
- Pending status
- Processing status  
- Completed status (with/without WebP)
- Failed status
- Non-existent task handling
- Expired task handling

### Download Endpoints
- File download functionality
- WebP file download
- Error handling for missing files
- Expiration checks

### Demo Interface
- Web route functionality
- Rate limiting
- File type validation
- Formatted response data

## Quality Assurance

All tests include:
- Positive and negative test cases
- Edge case handling
- Error condition testing
- Data validation
- Security considerations
- Performance implications

## Continuous Integration

Tests are designed to run in CI/CD environments with:
- No external dependencies
- Deterministic results
- Fast execution
- Clear failure reporting 