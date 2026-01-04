# Queue System Documentation

## Overview

The Conduit Queue System provides a database-driven job queue perfect for shared hosting environments where Redis and Supervisor are not available. It supports:

- **Database-backed storage** (MySQL/SQLite compatible)
- **HTTP-triggered processing** for external cron services
- **Piggyback processing** to process jobs after API requests
- **Retry mechanism** for failed jobs
- **Multiple queues** support
- **CLI commands** for management

## Quick Start

### 1. Create the Queue Tables

```bash
php conduit queue:table
```

Or run the migration:
```bash
php conduit migrate
```

### 2. Create a Job

```php
<?php

namespace App\Jobs;

use Conduit\Queue\Job;

class SendWelcomeEmail extends Job
{
    public string $queue = 'emails';
    public int $tries = 3;
    public int $retryAfter = 300; // 5 minutes
    
    public function __construct(
        protected string $email,
        protected string $name
    ) {}
    
    public function handle(): void
    {
        // Send the email
        mail($this->email, 'Welcome!', "Hello {$this->name}!");
    }
    
    public function failed(\Throwable $exception): void
    {
        // Log the failure
        error_log("Failed to send welcome email to {$this->email}");
    }
}
```

### 3. Dispatch Jobs

```php
// Dispatch immediately
SendWelcomeEmail::dispatch('user@example.com', 'John');

// Dispatch with delay (in seconds)
SendWelcomeEmail::dispatchAfter(3600, 'user@example.com', 'John');

// Dispatch to a specific queue
(new SendWelcomeEmail('user@example.com', 'John'))
    ->onQueue('high-priority')
    ->dispatch();
```

## Processing Jobs

### Option 1: CLI Worker (for VPS/Dedicated)

Process jobs continuously:
```bash
php conduit queue:work
```

Process with limits:
```bash
php conduit queue:work --limit=10 --timeout=30
```

Process specific queue:
```bash
php conduit queue:work --queue=emails
```

### Option 2: HTTP Endpoint (for Shared Hosting + External Cron)

Set up a cron job (e.g., via cron-job.org) to hit:
```
POST /api/queue/process?token=YOUR_QUEUE_TOKEN&limit=10&timeout=25
```

Configuration in `.env`:
```env
QUEUE_TOKEN=your-secret-token-here
```

### Option 3: Piggyback Processing (Automatic)

Enable in `.env`:
```env
QUEUE_PIGGYBACK=true
```

The queue will automatically process 2-3 jobs after each successful API request.

## Configuration

`config/queue.php`:

```php
return [
    'default' => env('QUEUE_CONNECTION', 'database'),
    'token' => env('QUEUE_TOKEN', null),
    
    'piggyback' => [
        'enabled' => env('QUEUE_PIGGYBACK', true),
        'max_jobs' => 2,
        'max_seconds' => 3,
    ],
    
    'retry' => [
        'times' => 3,
        'delay' => 60,
    ],
];
```

## CLI Commands

### Process Queue
```bash
php conduit queue:work [--queue=default] [--limit=0] [--timeout=0]
```

### View Failed Jobs
```bash
php conduit queue:failed
```

### Retry Failed Jobs
```bash
# Retry specific job
php conduit queue:retry 5

# Retry all failed jobs
php conduit queue:retry all
```

### Clear Failed Jobs
```bash
php conduit queue:clear
```

### Create Queue Tables
```bash
php conduit queue:table
```

## HTTP Endpoints

### Process Queue
```
POST /api/queue/process
Parameters:
  - token (required): Queue token from config
  - queue (optional): Queue name (default: "default")
  - limit (optional): Max jobs to process (default: 10, max: 50)
  - timeout (optional): Max seconds to run (default: 25, max: 55)

Response:
{
  "success": true,
  "processed": 5,
  "duration_ms": 123.45,
  "queue": "default",
  "pending": 10,
  "failed": 2
}
```

### Queue Stats
```
GET /api/queue/stats?token=YOUR_TOKEN

Response:
{
  "success": true,
  "queues": {
    "default": {"size": 15, "pending": 10},
    "emails": {"size": 5, "pending": 3},
    "notifications": {"size": 0, "pending": 0}
  },
  "failed": 2
}
```

## Job Properties

### Basic Configuration
```php
class MyJob extends Job
{
    public string $queue = 'default';     // Queue name
    public int $tries = 3;                // Max attempts
    public int $retryAfter = 60;          // Seconds between retries
}
```

### Methods

- `handle()`: Execute the job (required)
- `failed(\Throwable $e)`: Called when job fails after all retries
- `tries()`: Get max retry attempts
- `retryAfter()`: Get retry delay in seconds

## Example: Email Queue

```php
<?php

namespace App\Jobs;

use Conduit\Queue\Job;

class SendEmailJob extends Job
{
    public string $queue = 'emails';
    public int $tries = 3;
    public int $retryAfter = 300;
    
    public function __construct(
        protected string $to,
        protected string $subject,
        protected string $body
    ) {}
    
    public function handle(): void
    {
        // Use your preferred email library
        mail($this->to, $this->subject, $this->body);
    }
    
    public function failed(\Throwable $exception): void
    {
        error_log("Failed to send email to {$this->to}: {$exception->getMessage()}");
        
        // Optionally notify admin
        mail('admin@example.com', 'Failed Email Job', 
            "Could not send email to {$this->to}");
    }
}

// Usage
SendEmailJob::dispatch('user@example.com', 'Welcome', 'Hello!');
```

## Database Schema

### jobs table
```sql
- id: Primary key
- queue: Queue name (indexed)
- payload: Serialized job data
- attempts: Number of attempts
- reserved_at: When job was picked up
- available_at: When job becomes available
- created_at: When job was created
```

### failed_jobs table
```sql
- id: Primary key
- queue: Queue name
- payload: Serialized job data
- exception: Exception message and stack trace
- failed_at: When job failed
```

## Best Practices

1. **Keep jobs small and focused** - One job should do one thing
2. **Make jobs idempotent** - Jobs should be safe to run multiple times
3. **Handle failures gracefully** - Implement the `failed()` method
4. **Use appropriate queues** - Separate high-priority from low-priority
5. **Monitor failed jobs** - Check `queue:failed` regularly
6. **Set reasonable retry limits** - Don't retry indefinitely
7. **Use delays for rate limiting** - Spread out API calls

## Troubleshooting

### Jobs not processing?
- Check if tables exist: `php conduit queue:table`
- Verify worker is running or cron job is active
- Check queue token in `.env`
- Look for errors in failed jobs: `php conduit queue:failed`

### Jobs failing silently?
- Implement the `failed()` method to log errors
- Check error logs
- Verify job dependencies are available

### Performance issues?
- Use multiple queues to prioritize jobs
- Increase worker processes (if using CLI)
- Adjust piggyback settings
- Consider upgrading to Redis queue (if available)

## Security

- **Never commit your QUEUE_TOKEN** - Keep it secret
- **Use HTTPS** for HTTP endpoints
- **Validate job data** - Don't trust serialized data blindly
- **Limit queue sizes** - Prevent memory issues
- **Monitor for abuse** - Check for suspicious job patterns
