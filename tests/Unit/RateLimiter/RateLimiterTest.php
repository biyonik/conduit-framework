<?php

declare(strict_types=1);

namespace Tests\Unit\RateLimiter;

use PHPUnit\Framework\TestCase;
use Conduit\RateLimiter\RateLimiter;
use Conduit\RateLimiter\Storage\ArrayStorage;

class RateLimiterTest extends TestCase
{
    protected RateLimiter $limiter;
    protected ArrayStorage $storage;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new ArrayStorage();
        $this->limiter = new RateLimiter($this->storage);
    }
    
    public function testAttemptAllowsWithinLimit(): void
    {
        $key = 'test-key';
        
        // Should allow first attempt
        $this->assertTrue($this->limiter->attempt($key, 5, 60));
        $this->assertEquals(1, $this->limiter->attempts($key));
    }
    
    public function testAttemptBlocksWhenExceeded(): void
    {
        $key = 'test-key';
        $maxAttempts = 3;
        
        // Make max attempts
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->assertTrue($this->limiter->attempt($key, $maxAttempts, 60));
        }
        
        // Next attempt should be blocked
        $this->assertFalse($this->limiter->attempt($key, $maxAttempts, 60));
    }
    
    public function testTooManyAttempts(): void
    {
        $key = 'test-key';
        
        $this->assertFalse($this->limiter->tooManyAttempts($key, 5));
        
        // Hit the limiter 5 times
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit($key, 60);
        }
        
        $this->assertTrue($this->limiter->tooManyAttempts($key, 5));
    }
    
    public function testRemaining(): void
    {
        $key = 'test-key';
        $maxAttempts = 10;
        
        $this->assertEquals(10, $this->limiter->remaining($key, $maxAttempts));
        
        $this->limiter->hit($key, 60);
        $this->assertEquals(9, $this->limiter->remaining($key, $maxAttempts));
        
        $this->limiter->hit($key, 60);
        $this->assertEquals(8, $this->limiter->remaining($key, $maxAttempts));
    }
    
    public function testClear(): void
    {
        $key = 'test-key';
        
        $this->limiter->hit($key, 60);
        $this->assertEquals(1, $this->limiter->attempts($key));
        
        $this->limiter->clear($key);
        $this->assertEquals(0, $this->limiter->attempts($key));
    }
    
    public function testAvailableIn(): void
    {
        $key = 'test-key';
        
        // No data yet
        $this->assertEquals(0, $this->limiter->availableIn($key));
        
        // Hit and check available time
        $this->limiter->hit($key, 60);
        $availableIn = $this->limiter->availableIn($key);
        
        // Should be close to 60 seconds (allow 1 second margin)
        $this->assertGreaterThanOrEqual(59, $availableIn);
        $this->assertLessThanOrEqual(60, $availableIn);
    }
    
    public function testRetryAfter(): void
    {
        $key = 'test-key';
        
        $this->assertNull($this->limiter->retryAfter($key));
        
        $this->limiter->hit($key, 60);
        $retryAfter = $this->limiter->retryAfter($key);
        
        $this->assertIsInt($retryAfter);
        $this->assertGreaterThan(time(), $retryAfter);
    }
}
