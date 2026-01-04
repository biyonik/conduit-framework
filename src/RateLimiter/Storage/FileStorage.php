<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\Storage;

use Conduit\RateLimiter\Contracts\LimiterStorageInterface;

class FileStorage implements LimiterStorageInterface
{
    protected string $directory;
    
    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');
        
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }
    
    public function get(string $key): ?array
    {
        $path = $this->getPath($key);
        
        if (!file_exists($path)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($path));
        
        if (!$data || $data['expires_at'] < time()) {
            @unlink($path);
            return null;
        }
        
        return $data;
    }
    
    public function increment(string $key, int $decaySeconds): int
    {
        $path = $this->getPath($key);
        $now = time();
        
        // Lock file for atomic operation
        $lockPath = $path . '.lock';
        $lock = fopen($lockPath, 'c');
        flock($lock, LOCK_EX);
        
        try {
            $existing = $this->get($key);
            
            if ($existing) {
                $data = [
                    'attempts' => $existing['attempts'] + 1,
                    'expires_at' => $existing['expires_at'],
                ];
            } else {
                $data = [
                    'attempts' => 1,
                    'expires_at' => $now + $decaySeconds,
                ];
            }
            
            file_put_contents($path, serialize($data));
            
            return $data['attempts'];
            
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockPath);
        }
    }
    
    public function forget(string $key): void
    {
        $path = $this->getPath($key);
        @unlink($path);
    }
    
    public function cleanup(): void
    {
        $files = glob($this->directory . '/ratelimit_*');
        $now = time();
        
        foreach ($files as $file) {
            if (str_ends_with($file, '.lock')) {
                continue;
            }
            
            $data = @unserialize(file_get_contents($file));
            
            if (!$data || ($data['expires_at'] ?? 0) < $now) {
                @unlink($file);
            }
        }
    }
    
    protected function getPath(string $key): string
    {
        return $this->directory . '/ratelimit_' . sha1($key);
    }
}
