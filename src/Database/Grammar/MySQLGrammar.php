<?php

declare(strict_types=1);

namespace Conduit\Database\Grammar;

use Conduit\Database\Schema\Blueprint;

/**
 * MySQL SQL Grammar
 *
 * MySQL-specific SQL dialect implementation.
 *
 * @package Conduit\Database\Grammar
 */
class MySQLGrammar extends Grammar
{
    /**
     * {@inheritdoc}
     *
     * MySQL kullanır backticks: `column_name`
     */
    public function wrap(string $value): string
    {
        // Eğer * ise wrap etme
        if ($value === '*') {
            return $value;
        }

        // Eğer zaten wrapped ise dönme
        if (str_starts_with($value, '`') && str_ends_with($value, '`')) {
            return $value;
        }

        // Table.column formatını handle et
        if (str_contains($value, '.')) {
            $parts = explode('.', $value);
            return implode('.', array_map(fn($part) => "`{$part}`", $parts));
        }

        // AS alias handling
        if (str_contains($value, ' as ')) {
            [$column, $alias] = explode(' as ', strtolower($value), 2);
            return $this->wrap(trim($column)) . ' AS ' . $this->wrap(trim($alias));
        }

        return "`{$value}`";
    }

    /**
     * {@inheritdoc}
     *
     * MySQL: LIMIT 10
     */
    protected function compileLimit(int $limit): string
    {
        return "LIMIT {$limit}";
    }

    /**
     * {@inheritdoc}
     *
     * MySQL: OFFSET 20
     */
    protected function compileOffset(int $offset): string
    {
        return "OFFSET {$offset}";
    }

    /**
     * {@inheritdoc}
     *
     * MySQL: TRUNCATE TABLE table_name
     */
    public function compileTruncate(string $table): string
    {
        $table = $this->wrapTable($table);
        return "TRUNCATE TABLE {$table}";
    }

    /**
     * Database oluştur (MySQL-specific)
     *
     * @param string $database Database adı
     * @param string $charset Charset (default: utf8mb4)
     * @param string $collation Collation (default: utf8mb4_unicode_ci)
     * @return string
     */
    public function compileCreateDatabase(
        string $database,
        string $charset = 'utf8mb4',
        string $collation = 'utf8mb4_unicode_ci'
    ): string {
        return "CREATE DATABASE IF NOT EXISTS `{$database}`
                CHARACTER SET {$charset}
                COLLATE {$collation}";
    }

    /**
     * Database sil (MySQL-specific)
     *
     * @param string $database Database adı
     * @return string
     */
    public function compileDropDatabase(string $database): string
    {
        return "DROP DATABASE IF EXISTS `{$database}`";
    }

    /**
     * Index oluştur
     *
     * @param string $table Tablo adı
     * @param string $indexName Index adı
     * @param array $columns İndexlenecek kolonlar
     * @return string
     */
    public function compileCreateIndex(string $table, string $indexName, array $columns): string
    {
        $table = $this->wrapTable($table);
        $columns = implode(', ', array_map(fn($col) => $this->wrap($col), $columns));

        return "CREATE INDEX `{$indexName}` ON {$table} ({$columns})";
    }

    /**
     * Unique index oluştur
     *
     * @param string $table Tablo adı
     * @param string $indexName Index adı
     * @param array $columns İndexlenecek kolonlar
     * @return string
     */
    public function compileCreateUniqueIndex(string $table, string $indexName, array $columns): string
    {
        $table = $this->wrapTable($table);
        $columns = implode(', ', array_map(fn($col) => $this->wrap($col), $columns));

        return "CREATE UNIQUE INDEX `{$indexName}` ON {$table} ({$columns})";
    }

    /**
     * Compile DROP INDEX statement (MySQL syntax)
     * 
     * @param Blueprint $blueprint Blueprint instance
     * @param array $command Command array
     * @return string DROP INDEX SQL
     */
     public function compileDropIndex(Blueprint $blueprint, array $command): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $index = is_array($command['index'])
            ? $this->createIndexName('index', $command['index'])
            : $command['index'];

        return "DROP INDEX `{$index}` ON {$table}";
    }
}
