<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\Storage;

use Conduit\RateLimiter\Contracts\LimiterStorageInterface;

/**
 * Array Storage for testing purposes
 */
class ArrayStorage implements LimiterStorageInterface
{
    /**
     * @var array<string, array>
     */
    protected array $storage = [];
    
    public function get(string $key): ?array
    {
        if (!isset($this->storage[$key])) {
            return null;
        }
        
        $data = $this->storage[$key];
        
        if ($data['expires_at'] < time()) {
            unset($this->storage[$key]);
            return null;
        }
        
        return $data;
    }
    
    public function increment(string $key, int $decaySeconds): int
    {
        $now = time();
        $existing = $this->get($key);
        
        if ($existing) {
            $this->storage[$key] = [
                'attempts' => $existing['attempts'] + 1,
                'expires_at' => $existing['expires_at'],
            ];
            
            return $this->storage[$key]['attempts'];
        }
        
        $this->storage[$key] = [
            'attempts' => 1,
            'expires_at' => $now + $decaySeconds,
        ];
        
        return 1;
    }
    
    public function forget(string $key): void
    {
        unset($this->storage[$key]);
    }
    
    public function cleanup(): void
    {
        $now = time();
        
        foreach ($this->storage as $key => $data) {
            if ($data['expires_at'] < $now) {
                unset($this->storage[$key]);
            }
        }
    }
}
