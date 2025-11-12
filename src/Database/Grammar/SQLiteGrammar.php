<?php

declare(strict_types=1);

namespace Conduit\Database\Grammar;

use Conduit\Database\Schema\Blueprint;

/**
 * SQLite SQL Grammar
 *
 * SQLite-specific SQL dialect implementation.
 *
 * @package Conduit\Database\Grammar
 */
class SQLiteGrammar extends Grammar
{
    /**
     * {@inheritdoc}
     *
     * SQLite kullanır double quotes: "column_name"
     */
    public function wrap(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return $value;
        }

        if (str_contains($value, '.')) {
            $parts = explode('.', $value);
            return implode('.', array_map(fn($part) => "\"{$part}\"", $parts));
        }

        if (str_contains($value, ' as ')) {
            [$column, $alias] = explode(' as ', strtolower($value), 2);
            return $this->wrap(trim($column)) . ' AS ' . $this->wrap(trim($alias));
        }

        return "\"{$value}\"";
    }

    /**
     * {@inheritdoc}
     */
    protected function compileLimit(int $limit): string
    {
        return "LIMIT {$limit}";
    }

    /**
     * {@inheritdoc}
     */
    protected function compileOffset(int $offset): string
    {
        return "OFFSET {$offset}";
    }

    /**
     * {@inheritdoc}
     *
     * SQLite: DELETE FROM table_name (TRUNCATE yok)
     */
    public function compileTruncate(string $table): string
    {
        // SQLite TRUNCATE desteklemez, DELETE kullanır
        $table = $this->wrapTable($table);
        return "DELETE FROM {$table}";
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

        return "CREATE INDEX IF NOT EXISTS \"{$indexName}\" ON {$table} ({$columns})";
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

        return "CREATE UNIQUE INDEX IF NOT EXISTS \"{$indexName}\" ON {$table} ({$columns})";
    }

    /**
     * Compile DROP INDEX statement (SQLite syntax)
     * 
     * @param Blueprint $blueprint Blueprint instance
     * @param array $command Command array
     * @return string DROP INDEX SQL
     */
    public function compileDropIndex(Blueprint $blueprint, array $command): string
    {
        $index = is_array($command['index'])
            ? $this->createIndexName('index', $command['index'])
            : $command['index'];

        return "DROP INDEX IF EXISTS \"{$index}\"";
    }
}
