<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\Storage;

use Conduit\Database\Connection;
use Conduit\RateLimiter\Contracts\LimiterStorageInterface;

class DatabaseStorage implements LimiterStorageInterface
{
    protected Connection $db;
    protected string $table = 'rate_limits';
    
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }
    
    public function get(string $key): ?array
    {
        $result = $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE `key` = ? AND expires_at > ?",
            [$key, time()]
        );
        
        if (!$result) {
            return null;
        }
        
        return [
            'attempts' => (int) $result['attempts'],
            'expires_at' => (int) $result['expires_at'],
        ];
    }
    
    public function increment(string $key, int $decaySeconds): int
    {
        $now = time();
        $expiresAt = $now + $decaySeconds;
        
        // Try to get existing
        $existing = $this->get($key);
        
        if ($existing) {
            // Update existing - keep original expiry
            $newAttempts = $existing['attempts'] + 1;
            
            $this->db->statement(
                "UPDATE {$this->table} SET attempts = ? WHERE `key` = ?",
                [$newAttempts, $key]
            );
            
            return $newAttempts;
        }
        
        // Insert new
        $this->db->statement(
            "INSERT INTO {$this->table} (`key`, attempts, expires_at, created_at) VALUES (?, 1, ?, ?)",
            [$key, $expiresAt, $now]
        );
        
        return 1;
    }
    
    public function forget(string $key): void
    {
        $this->db->statement(
            "DELETE FROM {$this->table} WHERE `key` = ?",
            [$key]
        );
    }
    
    public function cleanup(): void
    {
        $this->db->statement(
            "DELETE FROM {$this->table} WHERE expires_at < ?",
            [time()]
        );
    }
}
