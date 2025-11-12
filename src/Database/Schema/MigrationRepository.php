<?php

declare(strict_types=1);

namespace Conduit\Database\Schema;

use Conduit\Database\Connection;
use Conduit\Database\QueryBuilder;

/**
 * Migration Repository
 * 
 * Migration tracking system - hangi migration'ların çalıştığını takip eder.
 * migrations tablosunu yönetir.
 * 
 * Table Structure:
 * - id (auto increment)
 * - migration (migration dosya adı)
 * - batch (rollback grouping için)
 * - executed_at (timestamp)
 * 
 * @package Conduit\Database\Schema
 */
class MigrationRepository
{
    /**
     * Database connection
     */
    protected Connection $connection;

    /**
     * Migration tracking table name
     */
    protected string $table = 'migrations';

    /**
     * Constructor
     * 
     * @param Connection $connection Database connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create the migration repository table
     * 
     * migrations tablosunu oluşturur (ilk kez çalıştırıldığında)
     * 
     * @return void
     */
    public function createRepository(): void
    {
        $grammar = $this->connection->getGrammar();
        $table = $grammar->wrapTable($this->table);

        // CREATE TABLE IF NOT EXISTS kullanarak idempotent yap
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $this->connection->statement($sql);
    }

    /**
     * Check if the repository exists
     * 
     * migrations tablosu var mı kontrol et
     * 
     * @return bool
     */
    public function repositoryExists(): bool
    {
        return Schema::hasTable($this->table);
    }

    /**
     * Get all ran migrations
     * 
     * Çalıştırılmış tüm migration'ların listesini döndürür
     * 
     * @return array<string> Migration file names
     */
    public function getRan(): array
    {
        $grammar = $this->connection->getGrammar();
        $table = $grammar->wrapTable($this->table);

        $sql = "SELECT migration FROM {$table} ORDER BY migration";
        $results = $this->connection->select($sql);

        return array_map(fn($row) => $row['migration'], $results);
    }

    /**
     * Get list of migrations (from files)
     * 
     * database/migrations/ klasöründeki tüm migration dosyalarını listele
     * 
     * @param string $path Migration dosyalarının path'i
     * @return array<string> Migration file names (sorted)
     */
    public function getMigrations(string $path): array
    {
        $files = glob($path . '/*.php');

        if ($files === false) {
            return [];
        }

        // Sadece dosya adlarını al (path olmadan)
        $migrations = array_map(function ($file) {
            return basename($file, '.php');
        }, $files);

        // Sırala (tarih prefix'ine göre otomatik sıralanır)
        sort($migrations);

        return $migrations;
    }

    /**
     * Get migrations grouped by batch number
     * 
     * Batch numarasına göre gruplandırılmış migration'ları döndür
     * 
     * @return array<int, array<string>> Batch => [migrations]
     */
    public function getMigrationBatches(): array
    {
        $grammar = $this->connection->getGrammar();
        $table = $grammar->wrapTable($this->table);

        $sql = "SELECT migration, batch FROM {$table} ORDER BY batch, migration";
        $results = $this->connection->select($sql);

        $batches = [];

        foreach ($results as $row) {
            $batch = (int) $row['batch'];
            $batches[$batch][] = $row['migration'];
        }

        return $batches;
    }

    /**
     * Get the last migration batch
     * 
     * Son çalıştırılan migration batch'ini döndür (rollback için)
     * 
     * @return array<string> Migration file names in last batch
     */
    public function getLastBatch(): array
    {
        $lastBatch = $this->getLastBatchNumber();

        if ($lastBatch === 0) {
            return [];
        }

        $grammar = $this->connection->getGrammar();
        $table = $grammar->wrapTable($this->table);

        $sql = "SELECT migration FROM {$table} WHERE batch = ? ORDER BY migration DESC";
        $results = $this->connection->select($sql, [$lastBatch]);

        return array_map(fn($row) => $row['migration'], $results);
    }

    /**
     * Log that a migration was run
     * 
     * Migration çalıştırıldığında migrations tablosuna kaydet
     * 
     * @param string $file Migration dosya adı
     * @param int $batch Batch numarası
     * @return void
     */
    public function log(string $file, int $batch): void
    {
        $grammar = $this->connection->getGrammar();
        $table = $grammar->wrapTable($this->table);

        $sql = "INSERT INTO {$table} (migration, batch) VALUES (?, ?)";
        $this->connection->insert($sql, [$file, $batch]);
    }

    /**
     * Remove a migration from the log
     * 
     * Migration rollback edildiğinde migrations tablosundan sil
     * 
     * @param string $file Migration dosya adı
     * @return void
     */
    public function delete(string $file): void
    {
        $grammar = $this->connection->getGrammar();
        $table = $grammar->wrapTable($this->table);

        $sql = "DELETE FROM {$table} WHERE migration = ?";
        $this->connection->delete($sql, [$file]);
    }

    /**
     * Get the next migration batch number
     * 
     * Bir sonraki batch numarasını döndür
     * 
     * @return int Next batch number
     */
    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last migration batch number
     * 
     * Son batch numarasını döndür (0 ise hiç migration çalışmamış)
     * 
     * @return int Last batch number
     */
    public function getLastBatchNumber(): int
    {
        $grammar = $this->connection->getGrammar();
        $table = $grammar->wrapTable($this->table);

        $sql = "SELECT MAX(batch) as max_batch FROM {$table}";
        $result = $this->connection->select($sql);

        if (empty($result) || $result[0]['max_batch'] === null) {
            return 0;
        }

        return (int) $result[0]['max_batch'];
    }

    /**
     * Get all migrations with their batch numbers
     * 
     * Tüm migration'ları batch numaralarıyla birlikte döndür
     * 
     * @return array<array{migration: string, batch: int}>
     */
    public function getAllMigrations(): array
    {
        $grammar = $this->connection->getGrammar();
        $table = $grammar->wrapTable($this->table);

        $sql = "SELECT migration, batch FROM {$table} ORDER BY batch, migration";
        return $this->connection->select($sql);
    }

    /**
     * Check if a migration has been run
     * 
     * Belirli bir migration çalıştırılmış mı kontrol et
     * 
     * @param string $file Migration dosya adı
     * @return bool
     */
    public function hasRun(string $file): bool
    {
        $grammar = $this->connection->getGrammar();
        $table = $grammar->wrapTable($this->table);

        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE migration = ?";
        $result = $this->connection->select($sql, [$file]);

        return !empty($result) && (int) $result[0]['count'] > 0;
    }

    /**
     * Get pending migrations
     * 
     * Henüz çalıştırılmamış migration'ları döndür
     * 
     * @param string $path Migration dosyalarının path'i
     * @return array<string> Pending migration file names
     */
    public function getPending(string $path): array
    {
        $ran = $this->getRan();
        $all = $this->getMigrations($path);

        return array_values(array_diff($all, $ran));
    }

    /**
     * Reset the migrations table
     * 
     * Tüm migration kayıtlarını sil (migrate:reset için)
     * 
     * @return void
     */
    public function reset(): void
    {
        $grammar = $this->connection->getGrammar();
        $table = $grammar->wrapTable($this->table);

        $sql = "DELETE FROM {$table}";
        $this->connection->statement($sql);
    }

    /**
     * Get the table name
     * 
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Set the table name
     * 
     * @param string $table Table name
     * @return void
     */
    public function setTable(string $table): void
    {
        $this->table = $table;
    }
}