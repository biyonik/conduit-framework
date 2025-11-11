<?php

declare(strict_types=1);

namespace Conduit\Database\Grammar;

/**
 * Base SQL Grammar
 *
 * QueryBuilder component'lerini SQL string'e çeviren abstract class.
 * Her database dialect (MySQL, SQLite, PostgreSQL) kendi Grammar'ını extend eder.
 *
 * @package Conduit\Database\Grammar
 */
abstract class Grammar
{
    /**
     * Table prefix (örn: "wp_" WordPress için)
     */
    protected string $tablePrefix = '';

    /**
     * SELECT query compile et
     *
     * @param array $components Query components (select, from, where, etc.)
     * @return string SQL query
     */
    public function compileSelect(array $components): string
    {
        $sql = [];

        // SELECT clause
        $sql[] = $this->compileSelectClause($components['select'] ?? ['*']);

        // FROM clause
        if (isset($components['from'])) {
            $sql[] = $this->compileFrom($components['from']);
        }

        // JOIN clauses
        if (isset($components['joins'])) {
            $sql[] = $this->compileJoins($components['joins']);
        }

        // WHERE clauses
        if (isset($components['wheres'])) {
            $sql[] = $this->compileWheres($components['wheres']);
        }

        // GROUP BY clause
        if (isset($components['groups'])) {
            $sql[] = $this->compileGroups($components['groups']);
        }

        // HAVING clause
        if (isset($components['havings'])) {
            $sql[] = $this->compileHavings($components['havings']);
        }

        // ORDER BY clause
        if (isset($components['orders'])) {
            $sql[] = $this->compileOrders($components['orders']);
        }

        // LIMIT clause
        if (isset($components['limit'])) {
            $sql[] = $this->compileLimit($components['limit']);
        }

        // OFFSET clause
        if (isset($components['offset'])) {
            $sql[] = $this->compileOffset($components['offset']);
        }

        return implode(' ', array_filter($sql));
    }

    /**
     * SELECT clause compile et
     *
     * @param array $columns Seçilecek kolonlar
     * @return string
     */
    protected function compileSelectClause(array $columns): string
    {
        if (empty($columns)) {
            $columns = ['*'];
        }

        $select = array_map(function ($column) {
            return $column === '*' ? '*' : $this->wrap($column);
        }, $columns);

        return 'SELECT ' . implode(', ', $select);
    }

    /**
     * FROM clause compile et
     *
     * @param string $table Tablo adı
     * @return string
     */
    protected function compileFrom(string $table): string
    {
        return 'FROM ' . $this->wrapTable($table);
    }

    /**
     * JOIN clauses compile et
     *
     * @param array $joins JOIN array'i
     * @return string
     */
    protected function compileJoins(array $joins): string
    {
        $sql = [];

        foreach ($joins as $join) {
            $type = strtoupper($join['type']); // INNER, LEFT, RIGHT
            $table = $this->wrapTable($join['table']);
            $first = $this->wrap($join['first']);
            $operator = $join['operator'];
            $second = $this->wrap($join['second']);

            $sql[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        }

        return implode(' ', $sql);
    }

    /**
     * WHERE clauses compile et
     *
     * @param array $wheres WHERE array'i
     * @return string
     */
    public function compileWheres(array $wheres): string
    {
        if (empty($wheres)) {
            return '';
        }

        $sql = [];

        foreach ($wheres as $index => $where) {
            $boolean = $index === 0 ? 'WHERE' : strtoupper($where['boolean']);
            $sql[] = $boolean . ' ' . $this->compileWhere($where);
        }

        return implode(' ', $sql);
    }

    /**
     * Tek bir WHERE condition compile et
     *
     * @param array $where WHERE condition
     * @return string
     */
    protected function compileWhere(array $where): string
    {
        return match ($where['type']) {
            'Basic' => $this->compileBasicWhere($where),
            'In' => $this->compileInWhere($where),
            'NotIn' => $this->compileNotInWhere($where),
            'Null' => $this->compileNullWhere($where),
            'NotNull' => $this->compileNotNullWhere($where),
            'Between' => $this->compileBetweenWhere($where),
            default => '',
        };
    }

    /**
     * Basic WHERE compile et (column = value)
     *
     * @param array $where WHERE data
     * @return string
     */
    protected function compileBasicWhere(array $where): string
    {
        return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ?';
    }

    /**
     * WHERE IN compile et
     *
     * @param array $where WHERE data
     * @return string
     */
    protected function compileInWhere(array $where): string
    {
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
        return $this->wrap($where['column']) . ' IN (' . $placeholders . ')';
    }

    /**
     * WHERE NOT IN compile et
     *
     * @param array $where WHERE data
     * @return string
     */
    protected function compileNotInWhere(array $where): string
    {
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
        return $this->wrap($where['column']) . ' NOT IN (' . $placeholders . ')';
    }

    /**
     * WHERE NULL compile et
     *
     * @param array $where WHERE data
     * @return string
     */
    protected function compileNullWhere(array $where): string
    {
        return $this->wrap($where['column']) . ' IS NULL';
    }

    /**
     * WHERE NOT NULL compile et
     *
     * @param array $where WHERE data
     * @return string
     */
    protected function compileNotNullWhere(array $where): string
    {
        return $this->wrap($where['column']) . ' IS NOT NULL';
    }

    /**
     * WHERE BETWEEN compile et
     *
     * @param array $where WHERE data
     * @return string
     */
    protected function compileBetweenWhere(array $where): string
    {
        return $this->wrap($where['column']) . ' BETWEEN ? AND ?';
    }

    /**
     * GROUP BY clause compile et
     *
     * @param array $groups GROUP BY kolonları
     * @return string
     */
    protected function compileGroups(array $groups): string
    {
        $columns = array_map(fn($col) => $this->wrap($col), $groups);
        return 'GROUP BY ' . implode(', ', $columns);
    }

    /**
     * HAVING clause compile et
     *
     * @param array $havings HAVING array'i
     * @return string
     */
    protected function compileHavings(array $havings): string
    {
        $sql = [];

        foreach ($havings as $index => $having) {
            $boolean = $index === 0 ? 'HAVING' : strtoupper($having['boolean']);
            $column = $this->wrap($having['column']);
            $operator = $having['operator'];
            $sql[] = "{$boolean} {$column} {$operator} ?";
        }

        return implode(' ', $sql);
    }

    /**
     * ORDER BY clause compile et
     *
     * @param array $orders ORDER BY array'i
     * @return string
     */
    protected function compileOrders(array $orders): string
    {
        $sql = [];

        foreach ($orders as $order) {
            $column = $this->wrap($order['column']);
            $direction = strtoupper($order['direction']);
            $sql[] = "{$column} {$direction}";
        }

        return 'ORDER BY ' . implode(', ', $sql);
    }

    /**
     * LIMIT clause compile et
     *
     * @param int $limit Limit değeri
     * @return string
     */
    abstract protected function compileLimit(int $limit): string;

    /**
     * OFFSET clause compile et
     *
     * @param int $offset Offset değeri
     * @return string
     */
    abstract protected function compileOffset(int $offset): string;

    /**
     * INSERT query compile et
     *
     * @param string $table Tablo adı
     * @param array $columns Kolonlar
     * @return string
     */
    public function compileInsert(string $table, array $columns): string
    {
        $table = $this->wrapTable($table);
        $columnList = implode(', ', array_map(fn($col) => $this->wrap($col), $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        return "INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})";
    }

    /**
     * UPDATE query compile et
     *
     * @param string $table Tablo adı
     * @param array $columns Update edilecek kolonlar
     * @return string
     */
    public function compileUpdate(string $table, array $columns): string
    {
        $table = $this->wrapTable($table);
        $sets = array_map(fn($col) => $this->wrap($col) . ' = ?', $columns);

        return "UPDATE {$table} SET " . implode(', ', $sets);
    }

    /**
     * DELETE query compile et
     *
     * @param string $table Tablo adı
     * @return string
     */
    public function compileDelete(string $table): string
    {
        $table = $this->wrapTable($table);
        return "DELETE FROM {$table}";
    }

    /**
     * TRUNCATE query compile et
     *
     * @param string $table Tablo adı
     * @return string
     */
    public function compileTruncate(string $table): string
    {
        $table = $this->wrapTable($table);
        return "TRUNCATE TABLE {$table}";
    }

    /**
     * Kolon adını wrap et (identifier quoting)
     *
     * MySQL: `column`, PostgreSQL: "column", SQLite: "column"
     *
     * @param string $value Wrap edilecek değer
     * @return string
     */
    abstract public function wrap(string $value): string;

    /**
     * Tablo adını wrap et (prefix ekleyerek)
     *
     * @param string $table Tablo adı
     * @return string
     */
    public function wrapTable(string $table): string
    {
        return $this->wrap($this->tablePrefix . $table);
    }

    /**
     * Table prefix set et
     *
     * @param string $prefix Prefix
     * @return void
     */
    public function setTablePrefix(string $prefix): void
    {
        $this->tablePrefix = $prefix;
    }

    /**
     * Table prefix al
     *
     * @return string
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }
}
