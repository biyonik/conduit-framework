<?php

declare(strict_types=1);

namespace Conduit\Database\Schema;

use Closure;
use Conduit\Database\Connection;
use Conduit\Database\Grammar\Grammar;

/**
 * Schema Facade - Database tablo işlemleri için facade
 * 
 * Kullanım:
 * - Schema::create('users', function($table) {...})
 * - Schema::table('users', function($table) {...})
 * - Schema::drop('users')
 * - Schema::hasTable('users')
 */
class Schema
{
    /**
     * Database connection instance
     */
    protected static ?Connection $connection = null;

    /**
     * Set the connection to be used
     * 
     * @param Connection $connection Database bağlantısı
     */
    public static function setConnection(Connection $connection): void
    {
        self::$connection = $connection;
    }

    /**
     * Get the connection instance
     * 
     * @throws \RuntimeException Connection set edilmemişse
     */
    protected static function getConnection(): Connection
    {
        if (self::$connection === null) {
            throw new \RuntimeException('Schema connection not set. Call Schema::setConnection() first.');
        }

        return self::$connection;
    }

    /**
     * Create a new table
     * 
     * @param string $table Tablo adı
     * @param Closure $callback Blueprint callback
     * @throws \RuntimeException Tablo zaten varsa veya SQL hatası olursa
     * 
     * @example
     * Schema::create('users', function (Blueprint $table) {
     *     $table->id();
     *     $table->string('name');
     *     $table->string('email')->unique();
     *     $table->timestamps();
     * });
     */
    public static function create(string $table, Closure $callback): void
    {
        $connection = self::getConnection();
        $grammar = $connection->getGrammar();

        // Blueprint oluştur
        $blueprint = new Blueprint($table);

        // Callback'i çalıştır (kullanıcı kolonları tanımlar)
        $callback($blueprint);

        // SQL statements'ları al
        $statements = $blueprint->toSql($grammar, $connection);

        // Her statement'ı çalıştır
        foreach ($statements as $statement) {
            $connection->statement($statement);
        }
    }

    /**
     * Modify an existing table
     * 
     * @param string $table Tablo adı
     * @param Closure $callback Blueprint callback
     * @throws \RuntimeException SQL hatası olursa
     * 
     * @example
     * Schema::table('users', function (Blueprint $table) {
     *     $table->string('phone')->nullable();
     *     $table->dropColumn('old_field');
     * });
     */
    public static function table(string $table, Closure $callback): void
    {
        $connection = self::getConnection();
        $grammar = $connection->getGrammar();

        // Blueprint oluştur (modify mode)
        $blueprint = new Blueprint($table, $modify = true);

        // Callback'i çalıştır
        $callback($blueprint);

        // SQL statements'ları al
        $statements = $blueprint->toSql($grammar, $connection);

        // Her statement'ı çalıştır
        foreach ($statements as $statement) {
            $connection->statement($statement);
        }
    }

    /**
     * Drop a table
     * 
     * @param string $table Tablo adı
     * @throws \RuntimeException Tablo yoksa veya SQL hatası olursa
     */
    public static function drop(string $table): void
    {
        $connection = self::getConnection();
        $grammar = $connection->getGrammar();

        $sql = $grammar->compileDropTable($table);
        $connection->statement($sql);
    }

    /**
     * Drop a table if it exists
     * 
     * @param string $table Tablo adı
     */
    public static function dropIfExists(string $table): void
    {
        $connection = self::getConnection();
        $grammar = $connection->getGrammar();

        $sql = $grammar->compileDropTableIfExists($table);
        $connection->statement($sql);
    }

    /**
     * Check if a table exists
     * 
     * @param string $table Tablo adı
     * @return bool Tablo varsa true
     */
    public static function hasTable(string $table): bool
    {
        $connection = self::getConnection();
        $grammar = $connection->getGrammar();

        $sql = $grammar->compileTableExists();
        $results = $connection->select($sql, [$table]);

        return count($results) > 0;
    }

    /**
     * Check if a column exists in a table
     * 
     * @param string $table Tablo adı
     * @param string $column Kolon adı
     * @return bool Kolon varsa true
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $connection = self::getConnection();
        $grammar = $connection->getGrammar();

        $sql = $grammar->compileColumnExists($table);
        $results = $connection->select($sql, [$column]);

        return count($results) > 0;
    }

    /**
     * Get all column names for a table
     * 
     * @param string $table Tablo adı
     * @return array<string> Kolon adları
     */
    public static function getColumnListing(string $table): array
    {
        $connection = self::getConnection();
        $grammar = $connection->getGrammar();

        $sql = $grammar->compileColumnListing($table);
        $results = $connection->select($sql);

        return array_map(fn($result) => $result['column_name'] ?? $result['Field'], $results);
    }

    /**
     * Rename a table
     * 
     * @param string $from Eski tablo adı
     * @param string $to Yeni tablo adı
     */
    public static function rename(string $from, string $to): void
    {
        $connection = self::getConnection();
        $grammar = $connection->getGrammar();

        $sql = $grammar->compileRenameTable($from, $to);
        $connection->statement($sql);
    }
}