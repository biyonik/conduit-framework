<?php

declare(strict_types=1);

namespace Conduit\Database\Schema;

use Conduit\Database\Connection;
use Conduit\Database\Exceptions\ConnectionException;
use Conduit\Database\Exceptions\QueryException;

/**
 * Database Schema Yöneticisi (Facade)
 *
 * Blueprint tanımlarını SQL'e çevirir ve execute eder
 * Dry-run mode destekli
 *
 * @package Conduit\Database\Schema
 */
class Schema
{
    /**
     * Static connection instance for facade pattern
     */
    private static ?Connection $staticConnection = null;

    /**
     * Dry-run mode aktif mi?
     */
    private bool $dryRun = false;

    /**
     * Dry-run mode'da toplanan SQL statements
     */
    private array $previewSql = [];

    public function __construct(
        private Connection $connection
    ) {}

    /**
     * Set static connection for facade pattern
     */
    public static function setConnection(Connection $connection): void
    {
        self::$staticConnection = $connection;
    }

    /**
     * Get schema instance
     */
    private static function getInstance(): self
    {
        if (self::$staticConnection === null) {
            self::$staticConnection = app('db.connection');
        }
        return new self(self::$staticConnection);
    }

    /**
     * Dry-run mode'u aktif et
     */
    final public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;
        $this->previewSql = [];

        return $this;
    }

    /**
     * Preview SQL statements'ları al
     */
    final public function getPreviewSql(): array
    {
        return $this->previewSql;
    }

    /**
     * Yeni tablo oluştur
     *
     * @param string $table Tablo adı
     * @param callable $callback Blueprint callback
     * @throws ConnectionException
     * @throws QueryException
     */
    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);

        // Callback'i çalıştır (kolonlar tanımlanır)
        $callback($blueprint);

        // SQL'e çevir
        $grammar = $this->connection->getGrammar();

        $statements = $blueprint->toSql($grammar, $this->connection);

        if ($this->dryRun) {
            // Dry-run: SQL'i topla, execute etme
            $this->previewSql = array_merge($this->previewSql, $statements);
        } else {
            // Gerçek execute
            foreach ($statements as $sql) {
                $this->connection->statement($sql);
            }
        }
    }

    /**
     * Tabloyu güncelle (kolon ekle/sil/değiştir)
     *
     * @param string $table Tablo adı
     * @param callable $callback Blueprint callback
     * @throws ConnectionException
     * @throws QueryException
     */
    final public function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, true); // modify mode

        $callback($blueprint);

        $grammar = $this->connection->getGrammar();

        $statements = $blueprint->toSql($grammar, $this->connection);

        if ($this->dryRun) {
            $this->previewSql = array_merge($this->previewSql, $statements);
        } else {
            foreach ($statements as $sql) {
                $this->connection->statement($sql);
            }
        }
    }

    /**
     * Tabloyu sil
     *
     * @param string $table Tablo adı
     * @throws ConnectionException
     * @throws QueryException
     */
    final public function drop(string $table): void
    {
        $sql = "DROP TABLE {$table}";

        if ($this->dryRun) {
            $this->previewSql[] = $sql;
        } else {
            $this->connection->statement($sql);
        }
    }

    /**
     * Tabloyu sil (varsa)
     *
     * @param string $table Tablo adı
     * @throws ConnectionException
     * @throws QueryException
     */
    final public function dropIfExists(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS {$table}";

        if ($this->dryRun) {
            $this->previewSql[] = $sql;
        } else {
            $this->connection->statement($sql);
        }
    }

    /**
     * Tablo var mı kontrol et
     *
     * @param string $table Tablo adı
     * @return bool
     * @throws ConnectionException
     * @throws QueryException
     */
    final public function hasTable(string $table): bool
    {
        // Dry-run mode'da false döndür (güvenli)
        if ($this->dryRun) {
            return false;
        }

        $grammar = $this->connection->getGrammar();

        // MySQL
        if ($grammar === 'mysql') {
            $result = $this->connection->select(
                "SHOW TABLES LIKE ?",
                [$table]
            );
            return !empty($result);
        }

        // SQLite
        if ($grammar === 'sqlite') {
            $result = $this->connection->select(
                "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
                [$table]
            );
            return !empty($result);
        }

        // PostgreSQL
        if ($grammar === 'pgsql') {
            $result = $this->connection->select(
                "SELECT tablename FROM pg_tables WHERE tablename=?",
                [$table]
            );
            return !empty($result);
        }

        return false;
    }

    /**
     * Tablodaki kolon var mı kontrol et
     *
     * @param string $table Tablo adı
     * @param string $column Kolon adı
     * @return bool
     */
    final public function hasColumn(string $table, string $column): bool
    {
        if ($this->dryRun) {
            return false;
        }

        $grammar = $this->connection->getGrammar();

        // MySQL
        if ($grammar === 'mysql') {
            $result = $this->connection->select(
                "SHOW COLUMNS FROM {$table} LIKE ?",
                [$column]
            );
            return !empty($result);
        }

        // SQLite
        if ($grammar === 'sqlite') {
            $result = $this->connection->select("PRAGMA table_info({$table})");
            foreach ($result as $col) {
                if ($col['name'] === $column) {
                    return true;
                }
            }
            return false;
        }

        // PostgreSQL
        if ($grammar === 'pgsql') {
            $result = $this->connection->select(
                "SELECT column_name FROM information_schema.columns WHERE table_name=? AND column_name=?",
                [$table, $column]
            );
            return !empty($result);
        }

        return false;
    }

    /**
     * Tüm tabloları getir
     *
     * @return array
     */
    public function getTables(): array
    {
        if ($this->dryRun) {
            return [];
        }

        $grammar = $this->connection->getGrammar();

        // MySQL
        if ($grammar === 'mysql') {
            $results = $this->connection->select("SHOW TABLES");
            return array_map(fn($r) => array_values((array)$r)[0], $results);
        }

        // SQLite
        if ($grammar === 'sqlite') {
            $results = $this->connection->select(
                "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
            );
            return array_column($results, 'name');
        }

        // PostgreSQL
        if ($grammar === 'pgsql') {
            $results = $this->connection->select(
                "SELECT tablename FROM pg_tables WHERE schemaname='public'"
            );
            return array_column($results, 'tablename');
        }

        return [];
    }

    /**
     * Handle static method calls
     */
    public static function __callStatic(string $method, array $arguments)
    {
        return self::getInstance()->$method(...$arguments);
    }
}
