# Rate Limiting System

A production-ready rate limiting system for the Conduit Framework that works without Redis, ideal for shared hosting environments.

## Features

- **Multiple Storage Backends**: File-based and Database storage (no Redis required)
- **Flexible Rate Limiting**: Fixed window algorithm with configurable limits
- **Multiple Key Generators**: IP-based, User-based, and Composite key generation
- **Middleware Integration**: Easy route-level throttling
- **Standard Headers**: Returns standard rate limit headers (X-RateLimit-*)
- **PHP 8 Attributes**: Support for controller-level rate limiting via attributes
- **CLI Management**: Command to clear expired entries
- **Atomic Operations**: Thread-safe file locking for concurrent requests

## Installation

The rate limiting system is included in the Conduit Framework. To enable it, add the service provider to your application configuration:

```php
// config/app.php
'providers' => [
    // ... other providers
    \Conduit\RateLimiter\RateLimitServiceProvider::class,
],
```

## Configuration

Configure the rate limiter in `config/ratelimiter.php`:

```php
return [
    'driver' => env('RATELIMITER_DRIVER', 'file'), // 'file' or 'database'
    
    'default' => [
        'max_attempts' => env('RATELIMIT_MAX_ATTEMPTS', 60),
        'decay_minutes' => env('RATELIMIT_DECAY_MINUTES', 1),
    ],
    
    'api' => [
        'max_attempts' => env('RATELIMIT_API_MAX_ATTEMPTS', 60),
        'decay_minutes' => env('RATELIMIT_API_DECAY_MINUTES', 1),
    ],
    
    'auth' => [
        'max_attempts' => env('RATELIMIT_AUTH_MAX_ATTEMPTS', 5),
        'decay_minutes' => env('RATELIMIT_AUTH_DECAY_MINUTES', 1),
    ],
];
```

### Environment Variables

```env
RATELIMITER_DRIVER=file

# Default limits
RATELIMIT_MAX_ATTEMPTS=60
RATELIMIT_DECAY_MINUTES=1

# API limits
RATELIMIT_API_MAX_ATTEMPTS=60
RATELIMIT_API_DECAY_MINUTES=1

# Authentication limits (stricter)
RATELIMIT_AUTH_MAX_ATTEMPTS=5
RATELIMIT_AUTH_DECAY_MINUTES=1
```

## Database Setup

If using database storage, run the migration:

```bash
php conduit migrate
```

This will create the `rate_limits` table with the following structure:
- `id` - Primary key
- `key` - Unique hash of the rate limit key
- `attempts` - Number of attempts made
- `expires_at` - Expiration timestamp
- `created_at` - Creation timestamp

## Usage

### Route Middleware

Apply rate limiting to specific routes or route groups:

```php
// routes/api.php

// Limit all API routes to 60 requests per minute
$router->middleware('throttle:60,1')->group(function ($router) {
    $router->get('/users', [UserController::class, 'index']);
    $router->post('/users', [UserController::class, 'store']);
});

// Stricter limit for authentication endpoints
$router->middleware('throttle:5,1')->group(function ($router) {
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/register', [AuthController::class, 'register']);
});

// Custom prefix for specific rate limiting
$router->middleware('throttle:100,5,premium')->group(function ($router) {
    $router->get('/premium/data', [PremiumController::class, 'data']);
});
```

**Middleware Parameters:**
1. `maxAttempts` - Maximum number of attempts (default: 60)
2. `decayMinutes` - Time window in minutes (default: 1)
3. `prefix` - Optional prefix for the rate limit key (default: '')

### PHP 8 Attributes

Use attributes directly on controller methods:

```php
use Conduit\RateLimiter\Attributes\RateLimit;
use Conduit\Http\JsonResponse;

class UserController
{
    #[RateLimit(maxAttempts: 100, decayMinutes: 1)]
    public function index(): JsonResponse
    {
        return JsonResponse::success(User::all());
    }
    
    #[RateLimit(maxAttempts: 10, decayMinutes: 5, prefix: 'create_user')]
    public function store(Request $request): JsonResponse
    {
        // Create user logic
        return JsonResponse::success($user, 201);
    }
}
```

### Manual Usage

Use the rate limiter directly in your code:

```php
use Conduit\RateLimiter\RateLimiter;

$limiter = app(RateLimiter::class);

$key = 'user:' . $userId . ':action:export';
$maxAttempts = 3;
$decaySeconds = 3600; // 1 hour

if ($limiter->tooManyAttempts($key, $maxAttempts)) {
    $retryAfter = $limiter->availableIn($key);
    throw new TooManyRequestsException($retryAfter);
}

$limiter->hit($key, $decaySeconds);

// Perform the action
performExport($userId);
```

### Available Methods

```php
// Check if rate limit is exceeded
$limiter->tooManyAttempts(string $key, int $maxAttempts): bool

// Attempt to hit the rate limiter (returns false if exceeded)
$limiter->attempt(string $key, int $maxAttempts, int $decaySeconds): bool

// Increment the counter
$limiter->hit(string $key, int $decaySeconds): int

// Get current attempt count
$limiter->attempts(string $key): int

// Get remaining attempts
$limiter->remaining(string $key, int $maxAttempts): int

// Get seconds until rate limit resets
$limiter->availableIn(string $key): int

// Get retry after timestamp
$limiter->retryAfter(string $key): ?int

// Clear a specific rate limit
$limiter->clear(string $key): void

// Clean up expired entries
$limiter->cleanup(): void
```

## Response Headers

When rate limiting is applied, the following headers are included in the response:

### Successful Request (200 OK)
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
```

### Rate Limited Request (429 Too Many Requests)
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1704358845
Retry-After: 45
```

## Storage Backends

### File Storage (Default)

Best for shared hosting environments. Files are stored in `storage/framework/ratelimiter/`.

**Pros:**
- No database required
- Works everywhere
- File locking ensures atomic operations

**Cons:**
- Slower than in-memory solutions
- Not ideal for high-traffic applications

### Database Storage

Better for applications with database access.

**Pros:**
- Centralized storage
- Better for distributed systems
- Automatic cleanup via index

**Cons:**
- Requires database
- Additional database queries

### Array Storage (Testing Only)

In-memory storage for testing. Data is lost when the process ends.

## CLI Commands

### Clear Expired Entries

Manually clean up expired rate limit entries:

```bash
php conduit ratelimit:clear
```

## Advanced Usage

### Custom Key Generators

Create custom key generators for specific use cases:

```php
use Conduit\RateLimiter\KeyGenerators\IpKeyGenerator;
use Conduit\RateLimiter\KeyGenerators\UserKeyGenerator;
use Conduit\RateLimiter\KeyGenerators\CompositeKeyGenerator;

// IP-based limiting
$ipGenerator = new IpKeyGenerator();
$key = $ipGenerator->generate($request);

// User-based limiting
$userGenerator = new UserKeyGenerator();
$key = $userGenerator->generate($request);

// Composite (IP + User)
$compositeGenerator = new CompositeKeyGenerator(
    new IpKeyGenerator(),
    new UserKeyGenerator()
);
$key = $compositeGenerator->generate($request);
```

### Custom Storage Implementation

Implement your own storage backend:

```php
use Conduit\RateLimiter\Contracts\LimiterStorageInterface;

class RedisStorage implements LimiterStorageInterface
{
    public function get(string $key): ?array { /* ... */ }
    public function increment(string $key, int $decaySeconds): int { /* ... */ }
    public function forget(string $key): void { /* ... */ }
    public function cleanup(): void { /* ... */ }
}
```

## Performance Considerations

1. **File Storage**: Uses file locking for atomic operations. Performance degrades with many concurrent requests.

2. **Database Storage**: Each hit requires 1-2 database queries. Consider adding database connection pooling.

3. **Cleanup**: 
   - File storage: Run cleanup periodically via cron
   - Database storage: Cleanup happens automatically due to expiry index

## Security

The rate limiter helps protect against:
- **Brute Force Attacks**: Limit login attempts
- **API Abuse**: Prevent excessive API calls
- **DoS Attacks**: Limit requests per IP
- **Resource Exhaustion**: Prevent expensive operations from being called too frequently

## Best Practices

1. **Different Limits for Different Actions**:
   - Authentication: 5-10 attempts per minute
   - API Reads: 60-100 requests per minute
   - API Writes: 30-60 requests per minute
   - Expensive Operations: 3-5 per hour

2. **Use Appropriate Decay Periods**:
   - Login attempts: 1-5 minutes
   - API rate limiting: 1 minute
   - Expensive operations: 1 hour

3. **Monitor Rate Limits**:
   - Log when limits are hit
   - Alert on unusual patterns
   - Adjust limits based on usage patterns

4. **Consider User Experience**:
   - Return clear error messages
   - Include Retry-After header
   - Show remaining attempts when possible

## Troubleshooting

### Rate limits not working

1. Ensure the service provider is registered
2. Check that middleware is applied to routes
3. Verify storage directory permissions (for file storage)
4. Check database connection (for database storage)

### Performance issues

1. Switch to database storage for better performance
2. Increase PHP memory limit for file storage
3. Run cleanup command regularly
4. Consider implementing Redis storage for high-traffic apps

### Storage directory not writable

```bash
chmod -R 755 storage/framework/ratelimiter
chown -R www-data:www-data storage/framework/ratelimiter
```

## License

This rate limiting system is part of the Conduit Framework and is licensed under the MIT License.
