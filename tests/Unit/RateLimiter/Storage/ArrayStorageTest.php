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
        // Create entry that expires in 0 seconds (immediately expired)
        $this->storage->increment('expired-key', 0);
        
        // Wait a moment to ensure it's expired
        sleep(1);
        
        // Create a valid entry
        $this->storage->increment('valid-key', 60);
        
        $this->storage->cleanup();
        
        $this->assertNull($this->storage->get('expired-key'));
        $this->assertNotNull($this->storage->get('valid-key'));
    }
}
