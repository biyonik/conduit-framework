# Route Caching System

## Overview

The Route Caching System eliminates runtime regex matching by pre-compiling routes into an optimized, cacheable format. This provides significant performance improvements in production environments.

## Features

- ✅ Pre-compiled regex patterns
- ✅ Cached parameter extraction
- ✅ Middleware information preserved
- ✅ Named routes support
- ✅ Optional parameters support
- ✅ OPcache integration
- ✅ Atomic file writes
- ✅ CLI commands for management

## Usage

### Caching Routes

```bash
php conduit route:cache
```

This command:
1. Loads all route definitions from `routes/web.php` and `routes/api.php`
2. Compiles them into an optimized format
3. Saves to `bootstrap/cache/routes.php`
4. Invalidates and precompiles OPcache

### Listing Routes

```bash
php conduit route:list
# or
php conduit routes
```

Displays a table of all registered routes with:
- HTTP methods
- URI patterns
- Named routes
- Actions (Controller@method or Closure)
- Middleware

### Clearing Cache

```bash
php conduit route:clear
```

Removes the route cache file, forcing the application to use dynamic route matching.

## Performance Impact

### Development Mode (No Cache)
- Routes parsed on every request
- Regex compiled dynamically
- Parameter extraction at runtime

### Production Mode (Cached)
- Routes loaded once from cache file
- Pre-compiled regex patterns
- Optimized parameter extraction
- OPcache acceleration

### Expected Performance Gains

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| Cold start | ~80ms | ~25ms | **68% faster** |
| Route matching | Per request | Cached | **1x only** |
| Memory usage | Higher | Lower | **~30% less** |

## Important Notes

### Closure Limitation

⚠️ **Closure-based routes cannot be fully cached** because PHP cannot serialize closures using `var_export()`.

In the cache, closures are marked as the string `"Closure"`:

```php
'action' => 'Closure'  // Cannot execute from cache
```

**Solution for Production:**

Convert closure routes to controller methods:

```php
// ❌ Development (won't cache properly)
$router->get('/users', function () {
    return User::all();
});

// ✅ Production (caches properly)
$router->get('/users', 'UserController@index');
```

### Automatic Cache Loading

The HTTP Kernel automatically loads the route cache if it exists in `bootstrap/cache/routes.php`.

### Development vs Production

**Development:**
- Don't cache routes (easier debugging)
- Routes are loaded from files on every request
- Changes take effect immediately

**Production:**
- Always cache routes
- Much faster performance
- Must clear cache after route changes

### Cache Invalidation

After modifying routes, clear the cache:

```bash
# After changing routes
php conduit route:clear
php conduit route:cache
```

## Best Practices

1. **Use controller methods in production** instead of closures
2. **Always cache routes in production** for best performance
3. **Clear cache after route changes** to ensure updates take effect
4. **Use named routes** for easier URL generation
5. **Test route cache** before deploying to production

## Example Workflow

```bash
# Development
php conduit route:list          # See all routes
# Make changes to routes/api.php
# Test changes (no cache needed)

# Production deployment
php conduit route:clear         # Clear old cache
php conduit route:cache         # Create new cache
# Deploy application
```
