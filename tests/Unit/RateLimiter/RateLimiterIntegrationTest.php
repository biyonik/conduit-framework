<?php

declare(strict_types=1);

namespace Tests\Unit\RateLimiter;

use PHPUnit\Framework\TestCase;
use Conduit\RateLimiter\RateLimiter;
use Conduit\RateLimiter\Storage\FileStorage;
use Conduit\RateLimiter\Storage\DatabaseStorage;
use Conduit\RateLimiter\Storage\ArrayStorage;

/**
 * Integration test showcasing real-world usage scenarios
 */
class RateLimiterIntegrationTest extends TestCase
{
    private string $tempDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/ratelimiter-test-' . uniqid();
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Cleanup temp directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->tempDir);
        }
    }
    
    public function testApiRateLimitingScenario(): void
    {
        // Scenario: API endpoint with 10 requests per minute
        $storage = new ArrayStorage();
        $limiter = new RateLimiter($storage);
        
        $userId = 'user-123';
        $endpoint = 'GET|/api/v1/users';
        $key = sha1($userId . '|' . $endpoint);
        $maxAttempts = 10;
        $decaySeconds = 60;
        
        // Simulate 10 successful requests
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($limiter->attempt($key, $maxAttempts, $decaySeconds));
        }
        
        // 11th request should be rate limited
        $this->assertFalse($limiter->attempt($key, $maxAttempts, $decaySeconds));
        $this->assertTrue($limiter->tooManyAttempts($key, $maxAttempts));
        $this->assertEquals(0, $limiter->remaining($key, $maxAttempts));
    }
    
    public function testLoginBruteForceProtection(): void
    {
        // Scenario: Login endpoint with 5 attempts per 5 minutes
        $storage = new ArrayStorage();
        $limiter = new RateLimiter($storage);
        
        $ipAddress = '192.168.1.100';
        $endpoint = 'POST|/auth/login';
        $key = sha1($ipAddress . '|' . $endpoint);
        $maxAttempts = 5;
        $decaySeconds = 300; // 5 minutes
        
        // Simulate 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($limiter->attempt($key, $maxAttempts, $decaySeconds));
        }
        
        // 6th attempt should be blocked
        $this->assertFalse($limiter->attempt($key, $maxAttempts, $decaySeconds));
        
        // Check retry information
        $availableIn = $limiter->availableIn($key);
        $this->assertGreaterThan(0, $availableIn);
        $this->assertLessThanOrEqual(300, $availableIn);
    }
    
    public function testFileStoragePersistence(): void
    {
        // Test that file storage persists across instances
        $storage1 = new FileStorage($this->tempDir);
        $limiter1 = new RateLimiter($storage1);
        
        $key = 'persistence-test';
        $limiter1->hit($key, 60);
        $limiter1->hit($key, 60);
        
        // Create new instance with same storage directory
        $storage2 = new FileStorage($this->tempDir);
        $limiter2 = new RateLimiter($storage2);
        
        // Should see the same attempts
        $this->assertEquals(2, $limiter2->attempts($key));
    }
    
    public function testDifferentUsersIndependentLimits(): void
    {
        // Test that different users have independent rate limits
        $storage = new ArrayStorage();
        $limiter = new RateLimiter($storage);
        
        $endpoint = 'GET|/api/v1/posts';
        $user1Key = sha1('user-1|' . $endpoint);
        $user2Key = sha1('user-2|' . $endpoint);
        $maxAttempts = 5;
        
        // User 1 makes 5 requests
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($limiter->attempt($user1Key, $maxAttempts, 60));
        }
        
        // User 1 is rate limited
        $this->assertTrue($limiter->tooManyAttempts($user1Key, $maxAttempts));
        
        // User 2 should still be able to make requests
        $this->assertFalse($limiter->tooManyAttempts($user2Key, $maxAttempts));
        $this->assertTrue($limiter->attempt($user2Key, $maxAttempts, 60));
    }
    
    public function testClearingSpecificUserLimit(): void
    {
        // Test clearing rate limit for a specific user (e.g., after password reset)
        $storage = new ArrayStorage();
        $limiter = new RateLimiter($storage);
        
        $key = 'user-password-reset';
        $maxAttempts = 3;
        
        // User exceeds rate limit
        for ($i = 0; $i < 3; $i++) {
            $limiter->hit($key, 60);
        }
        
        $this->assertTrue($limiter->tooManyAttempts($key, $maxAttempts));
        
        // Admin clears the rate limit
        $limiter->clear($key);
        
        // User can try again
        $this->assertFalse($limiter->tooManyAttempts($key, $maxAttempts));
        $this->assertEquals(0, $limiter->attempts($key));
    }
}
