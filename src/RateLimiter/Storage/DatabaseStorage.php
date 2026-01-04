<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\Storage;

use Conduit\Database\Connection;
use Conduit\RateLimiter\Contracts\LimiterStorageInterface;

class DatabaseStorage implements LimiterStorageInterface
{
    protected Connection $db;
    protected string $table = 'rate_limits';

    public function __construct(Connection $db, string $table = 'rate_limits')
    {
        // SECURITY: Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException(
                "Invalid table name: '{$table}'. " .
                "Only alphanumeric characters and underscores are allowed."
            );
        }

        $this->db = $db;
        $this->table = $table;
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

        // SECURITY FIX: Use atomic increment to prevent race conditions
        // Try to update existing record first (atomic operation)
        $updated = $this->db->statement(
            "UPDATE {$this->table} SET attempts = attempts + 1 WHERE `key` = ? AND expires_at > ?",
            [$key, $now]
        );

        if ($updated > 0) {
            // Record was updated, get the new value
            $result = $this->db->selectOne(
                "SELECT attempts FROM {$this->table} WHERE `key` = ?",
                [$key]
            );
            return (int) $result['attempts'];
        }

        // Record doesn't exist or expired, try to insert new
        // Use INSERT IGNORE to handle concurrent inserts
        try {
            $this->db->statement(
                "INSERT INTO {$this->table} (`key`, attempts, expires_at, created_at)
                 VALUES (?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE attempts = attempts + 1, expires_at = ?",
                [$key, $expiresAt, $now, $expiresAt]
            );
        } catch (\Exception $e) {
            // If duplicate key error occurs, retry the update
            $updated = $this->db->statement(
                "UPDATE {$this->table} SET attempts = attempts + 1 WHERE `key` = ?",
                [$key]
            );
        }

        // Get the final value
        $result = $this->db->selectOne(
            "SELECT attempts FROM {$this->table} WHERE `key` = ?",
            [$key]
        );

        return (int) ($result['attempts'] ?? 1);
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
