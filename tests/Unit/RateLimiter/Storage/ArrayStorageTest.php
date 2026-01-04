<?php

declare(strict_types=1);

namespace Tests\Unit\RateLimiter\Storage;

use PHPUnit\Framework\TestCase;
use Conduit\RateLimiter\Storage\ArrayStorage;

class ArrayStorageTest extends TestCase
{
    protected ArrayStorage $storage;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new ArrayStorage();
    }
    
    public function testGetReturnsNullForNonExistentKey(): void
    {
        $this->assertNull($this->storage->get('non-existent'));
    }
    
    public function testIncrementCreatesNewEntry(): void
    {
        $count = $this->storage->increment('test-key', 60);
        
        $this->assertEquals(1, $count);
        
        $data = $this->storage->get('test-key');
        $this->assertIsArray($data);
        $this->assertEquals(1, $data['attempts']);
        $this->assertGreaterThan(time(), $data['expires_at']);
    }
    
    public function testIncrementIncrementsExisting(): void
    {
        $this->storage->increment('test-key', 60);
        $count = $this->storage->increment('test-key', 60);
        
        $this->assertEquals(2, $count);
    }
    
    public function testForgetRemovesKey(): void
    {
        $this->storage->increment('test-key', 60);
        $this->assertNotNull($this->storage->get('test-key'));
        
        $this->storage->forget('test-key');
        $this->assertNull($this->storage->get('test-key'));
    }
    
    public function testCleanupRemovesExpiredEntries(): void
    {
        // Create entry with very short expiry (1 second)
        $this->storage->increment('short-lived-key', 1);
        
        // Create a valid entry with longer expiry
        $this->storage->increment('valid-key', 60);
        
        // Wait for short expiry to pass
        sleep(2);
        
        $this->storage->cleanup();
        
        $this->assertNull($this->storage->get('short-lived-key'));
        $this->assertNotNull($this->storage->get('valid-key'));
    }
}
