<?php

declare(strict_types=1);

namespace Conduit\Database\Grammar;

use Conduit\Database\Schema\Blueprint;
use Conduit\Database\Schema\ColumnDefinition;

/**
 * Base SQL Grammar
 *
 * QueryBuilder component'lerini ve Schema Blueprint'lerini SQL string'e çeviren abstract class.
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

    // ==================== QUERY BUILDER COMPILATION ====================

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

    // ==================== SCHEMA BLUEPRINT COMPILATION ====================

    /**
     * Compile a CREATE TABLE statement from Blueprint
     *
     * @param Blueprint $blueprint Blueprint instance
     * @return string CREATE TABLE SQL
     */
    public function compileCreateTable(Blueprint $blueprint): string
    {
        $table = $this->wrapTable($blueprint->getTable());

        // Kolonları compile et
        $columns = $this->getColumns($blueprint);

        // Primary key, unique, index komutlarını al
        $primaryKey = $this->getPrimaryKey($blueprint);

        // Tüm column definitions ve constraints'leri birleştir
        $definitions = array_merge($columns, array_filter([$primaryKey]));

        $columnDefs = implode(', ', $definitions);

        return "CREATE TABLE {$table} ({$columnDefs})";
    }

    /**
     * Get the column definitions for the blueprint
     *
     * @param Blueprint $blueprint Blueprint instance
     * @return array<string> Column SQL definitions
     */
    protected function getColumns(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            $columns[] = $this->getColumnDefinition($column);
        }

        return $columns;
    }

    /**
     * Get a single column definition
     *
     * @param ColumnDefinition $column Column definition
     * @return string Column SQL definition
     */
    protected function getColumnDefinition(ColumnDefinition $column): string
    {
        $attributes = $column->getAttributes();
        $name = $this->wrap($attributes['name']);
        $type = $this->getType($column);

        $sql = "{$name} {$type}";

        // Modifiers ekle (unsigned, nullable, default, etc.)
        $sql .= $this->modifyUnsigned($column);
        $sql .= $this->modifyNullable($column);
        $sql .= $this->modifyDefault($column);
        $sql .= $this->modifyAutoIncrement($column);
        $sql .= $this->modifyComment($column);

        return trim($sql);
    }

    /**
     * Get the SQL type for a column
     *
     * @param ColumnDefinition $column Column definition
     * @return string SQL type
     */
    protected function getType(ColumnDefinition $column): string
    {
        $attributes = $column->getAttributes();
        $type = $attributes['type'];

        // Type'a göre uygun metodu çağır
        $method = 'type' . ucfirst($type);

        if (method_exists($this, $method)) {
            return $this->$method($column);
        }

        throw new \RuntimeException("Type [{$type}] is not supported.");
    }

    /**
     * Get PRIMARY KEY constraint
     *
     * @param Blueprint $blueprint Blueprint instance
     * @return string|null PRIMARY KEY SQL
     */
    protected function getPrimaryKey(Blueprint $blueprint): ?string
    {
        // Önce auto-increment primary key olan kolonları bul
        foreach ($blueprint->getColumns() as $column) {
            $attributes = $column->getAttributes();
            if (isset($attributes['primary']) && $attributes['primary']) {
                $name = $this->wrap($attributes['name']);
                return "PRIMARY KEY ({$name})";
            }
        }

        // Sonra explicit primary key command'ını bul
        foreach ($blueprint->getCommands() as $command) {
            if ($command['name'] === 'primary') {
                $columns = implode(', ', array_map([$this, 'wrap'], $command['columns']));
                return "PRIMARY KEY ({$columns})";
            }
        }

        return null;
    }

    // ==================== COLUMN TYPE METHODS ====================

    /**
     * Create the column definition for a bigIncrements type
     */
    protected function typeBigIncrements(ColumnDefinition $column): string
    {
        return 'BIGINT UNSIGNED';
    }

    /**
     * Create the column definition for an increments type
     */
    protected function typeIncrements(ColumnDefinition $column): string
    {
        return 'INT UNSIGNED';
    }

    /**
     * Create the column definition for a uuid type
     */
    protected function typeUuid(ColumnDefinition $column): string
    {
        return 'CHAR(36)';
    }

    /**
     * Create the column definition for a string type
     */
    protected function typeString(ColumnDefinition $column): string
    {
        $length = $column->get('length', 255);
        return "VARCHAR({$length})";
    }

    /**
     * Create the column definition for a char type
     */
    protected function typeChar(ColumnDefinition $column): string
    {
        $length = $column->get('length', 255);
        return "CHAR({$length})";
    }

    /**
     * Create the column definition for a text type
     */
    protected function typeText(ColumnDefinition $column): string
    {
        return 'TEXT';
    }

    /**
     * Create the column definition for a mediumText type
     */
    protected function typeMediumText(ColumnDefinition $column): string
    {
        return 'MEDIUMTEXT';
    }

    /**
     * Create the column definition for a longText type
     */
    protected function typeLongText(ColumnDefinition $column): string
    {
        return 'LONGTEXT';
    }

    /**
     * Create the column definition for an integer type
     */
    protected function typeInteger(ColumnDefinition $column): string
    {
        return 'INT';
    }

    /**
     * Create the column definition for a tinyInteger type
     */
    protected function typeTinyInteger(ColumnDefinition $column): string
    {
        return 'TINYINT';
    }

    /**
     * Create the column definition for a smallInteger type
     */
    protected function typeSmallInteger(ColumnDefinition $column): string
    {
        return 'SMALLINT';
    }

    /**
     * Create the column definition for a mediumInteger type
     */
    protected function typeMediumInteger(ColumnDefinition $column): string
    {
        return 'MEDIUMINT';
    }

    /**
     * Create the column definition for a bigInteger type
     */
    protected function typeBigInteger(ColumnDefinition $column): string
    {
        return 'BIGINT';
    }

    /**
     * Create the column definition for a float type
     */
    protected function typeFloat(ColumnDefinition $column): string
    {
        $total = $column->get('total', 8);
        $places = $column->get('places', 2);
        return "FLOAT({$total}, {$places})";
    }

    /**
     * Create the column definition for a double type
     */
    protected function typeDouble(ColumnDefinition $column): string
    {
        return 'DOUBLE';
    }

    /**
     * Create the column definition for a decimal type
     */
    protected function typeDecimal(ColumnDefinition $column): string
    {
        $total = $column->get('total', 8);
        $places = $column->get('places', 2);
        return "DECIMAL({$total}, {$places})";
    }

    /**
     * Create the column definition for a boolean type
     */
    protected function typeBoolean(ColumnDefinition $column): string
    {
        return 'TINYINT(1)';
    }

    /**
     * Create the column definition for a date type
     */
    protected function typeDate(ColumnDefinition $column): string
    {
        return 'DATE';
    }

    /**
     * Create the column definition for a datetime type
     */
    protected function typeDatetime(ColumnDefinition $column): string
    {
        $precision = $column->get('precision', 0);
        return $precision > 0 ? "DATETIME({$precision})" : 'DATETIME';
    }

    /**
     * Create the column definition for a timestamp type
     */
    protected function typeTimestamp(ColumnDefinition $column): string
    {
        $precision = $column->get('precision', 0);
        return $precision > 0 ? "TIMESTAMP({$precision})" : 'TIMESTAMP';
    }

    /**
     * Create the column definition for a time type
     */
    protected function typeTime(ColumnDefinition $column): string
    {
        $precision = $column->get('precision', 0);
        return $precision > 0 ? "TIME({$precision})" : 'TIME';
    }

    /**
     * Create the column definition for a year type
     */
    protected function typeYear(ColumnDefinition $column): string
    {
        return 'YEAR';
    }

    /**
     * Create the column definition for a json type
     */
    protected function typeJson(ColumnDefinition $column): string
    {
        return 'JSON';
    }

    /**
     * Create the column definition for a jsonb type
     */
    protected function typeJsonb(ColumnDefinition $column): string
    {
        return 'JSON'; // MySQL uses JSON for both
    }

    /**
     * Create the column definition for a binary type
     */
    protected function typeBinary(ColumnDefinition $column): string
    {
        return 'BLOB';
    }

    /**
     * Create the column definition for an enum type
     */
    protected function typeEnum(ColumnDefinition $column): string
    {
        $allowed = $column->get('allowed', []);
        $values = implode(', ', array_map(fn($val) => "'{$val}'", $allowed));
        return "ENUM({$values})";
    }

    /**
     * Create the column definition for a set type
     */
    protected function typeSet(ColumnDefinition $column): string
    {
        $allowed = $column->get('allowed', []);
        $values = implode(', ', array_map(fn($val) => "'{$val}'", $allowed));
        return "SET({$values})";
    }

    // ==================== COLUMN MODIFIERS ====================

    /**
     * Add the unsigned modifier to a column
     */
    protected function modifyUnsigned(ColumnDefinition $column): string
    {
        if ($column->get('unsigned', false)) {
            return ' UNSIGNED';
        }
        return '';
    }

    /**
     * Add the nullable modifier to a column
     */
    protected function modifyNullable(ColumnDefinition $column): string
    {
        if ($column->get('nullable', false)) {
            return ' NULL';
        }
        return ' NOT NULL';
    }

    /**
     * Add the default modifier to a column
     */
    protected function modifyDefault(ColumnDefinition $column): string
    {
        if ($column->get('default') !== null) {
            $default = $column->get('default');

            // Boolean değerleri integer'a çevir
            if (is_bool($default)) {
                $default = (int) $default;
            }

            // String değerleri quote'la
            if (is_string($default)) {
                $default = "'{$default}'";
            }

            return " DEFAULT {$default}";
        }
        return '';
    }

    /**
     * Add the auto-increment modifier to a column
     */
    protected function modifyAutoIncrement(ColumnDefinition $column): string
    {
        if ($column->get('autoIncrement', false)) {
            return ' AUTO_INCREMENT';
        }
        return '';
    }

    /**
     * Add the comment modifier to a column
     */
    protected function modifyComment(ColumnDefinition $column): string
    {
        if ($comment = $column->get('comment')) {
            return " COMMENT '{$comment}'";
        }
        return '';
    }

    // ==================== ALTER TABLE METHODS ====================

    /**
     * Compile an ALTER TABLE ADD COLUMN statement
     *
     * @param Blueprint $blueprint Blueprint instance
     * @param ColumnDefinition $column Column definition
     * @return string ALTER TABLE SQL
     */
    public function compileAddColumn(Blueprint $blueprint, ColumnDefinition $column): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $definition = $this->getColumnDefinition($column);

        $sql = "ALTER TABLE {$table} ADD {$definition}";

        // AFTER modifier varsa ekle
        if ($after = $column->get('after')) {
            $sql .= ' AFTER ' . $this->wrap($after);
        }

        // FIRST modifier varsa ekle
        if ($column->get('first', false)) {
            $sql .= ' FIRST';
        }

        return $sql;
    }

    // ==================== CONSTRAINT COMPILATION ====================

    /**
     * Compile a unique index command
     */
    public function compileUnique(Blueprint $blueprint, array $command): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $columns = implode(', ', array_map([$this, 'wrap'], $command['columns']));
        $index = $command['index'] ?? $this->createIndexName('unique', $command['columns']);

        return "ALTER TABLE {$table} ADD UNIQUE {$index} ({$columns})";
    }

    /**
     * Compile an index command
     */
    public function compileIndex(Blueprint $blueprint, array $command): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $columns = implode(', ', array_map([$this, 'wrap'], $command['columns']));
        $index = $command['index'] ?? $this->createIndexName('index', $command['columns']);

        return "ALTER TABLE {$table} ADD INDEX {$index} ({$columns})";
    }

    /**
     * Compile a foreign key command
     */
    public function compileForeign(Blueprint $blueprint, array $command): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $columns = implode(', ', array_map([$this, 'wrap'], $command['columns']));
        $on = $this->wrapTable($command['on']);
        $references = $command['references'];

        $sql = "ALTER TABLE {$table} ADD CONSTRAINT ";

        // Constraint adı
        if (isset($command['index'])) {
            $sql .= $command['index'];
        } else {
            $sql .= $this->createIndexName('foreign', $command['columns']);
        }

        $sql .= " FOREIGN KEY ({$columns}) REFERENCES {$on} ({$references})";

        // ON DELETE
        if (isset($command['onDelete'])) {
            $sql .= " ON DELETE " . strtoupper($command['onDelete']);
        }

        // ON UPDATE
        if (isset($command['onUpdate'])) {
            $sql .= " ON UPDATE " . strtoupper($command['onUpdate']);
        }

        return $sql;
    }

    /**
     * Compile a drop column command
     */
    public function compileDropColumn(Blueprint $blueprint, array $command): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $columns = implode(', ', array_map(function($col) {
            return 'DROP ' . $this->wrap($col);
        }, $command['columns']));

        return "ALTER TABLE {$table} {$columns}";
    }

    /**
     * Compile a drop primary key command
     */
    public function compileDropPrimary(Blueprint $blueprint, array $command): string
    {
        $table = $this->wrapTable($blueprint->getTable());

        return "ALTER TABLE {$table} DROP PRIMARY KEY";
    }

    /**
     * Compile a drop unique key command
     */
    public function compileDropUnique(Blueprint $blueprint, array $command): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $index = is_array($command['index'])
            ? $this->createIndexName('unique', $command['index'])
            : $command['index'];

        return "ALTER TABLE {$table} DROP INDEX {$index}";
    }

    /**
     * Compile a drop index command
     */
    public function compileDropIndex(Blueprint $blueprint, array $command): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $index = is_array($command['index'])
            ? $this->createIndexName('index', $command['index'])
            : $command['index'];

        return "ALTER TABLE {$table} DROP INDEX {$index}";
    }

    /**
     * Compile a drop foreign key command
     */
    public function compileDropForeign(Blueprint $blueprint, array $command): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $index = is_array($command['index'])
            ? $this->createIndexName('foreign', $command['index'])
            : $command['index'];

        return "ALTER TABLE {$table} DROP FOREIGN KEY {$index}";
    }

    /**
     * Compile a rename column command
     */
    public function compileRenameColumn(Blueprint $blueprint, array $command): string
    {
        $table = $this->wrapTable($blueprint->getTable());
        $from = $this->wrap($command['from']);
        $to = $this->wrap($command['to']);

        return "ALTER TABLE {$table} RENAME COLUMN {$from} TO {$to}";
    }

    /**
     * Compile a rename table command
     */
    public function compileRename(Blueprint $blueprint, array $command): string
    {
        $from = $this->wrapTable($blueprint->getTable());
        $to = $this->wrapTable($command['to']);

        return "RENAME TABLE {$from} TO {$to}";
    }

    // ==================== UTILITY METHODS ====================

    /**
     * Compile DROP TABLE statement
     */
    public function compileDropTable(string $table): string
    {
        return 'DROP TABLE ' . $this->wrapTable($table);
    }

    /**
     * Compile DROP TABLE IF EXISTS statement
     */
    public function compileDropTableIfExists(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrapTable($table);
    }

    /**
     * Compile RENAME TABLE statement
     */
    public function compileRenameTable(string $from, string $to): string
    {
        return 'RENAME TABLE ' . $this->wrapTable($from) . ' TO ' . $this->wrapTable($to);
    }

    /**
     * Compile table exists query
     */
    public function compileTableExists(): string
    {
        return "SELECT * FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
    }

    /**
     * Compile column exists query
     */
    public function compileColumnExists(string $table): string
    {
        return "SELECT * FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = ?";
    }

    /**
     * Compile get column listing query
     */
    public function compileColumnListing(string $table): string
    {
        return "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$table}'";
    }

    /**
     * Create an index name from type and columns
     *
     * @param string $type Index tipi (unique, index, foreign)
     * @param array $columns Kolon adları
     * @return string Index adı
     */
    protected function createIndexName(string $type, array $columns): string
    {
        $name = strtolower(implode('_', $columns));
        return "{$name}_{$type}";
    }

    // ==================== ABSTRACT METHODS ====================

    /**
     * Kolon adını wrap et (identifier quoting)
     *
     * MySQL: `column`, PostgreSQL: "column", SQLite: "column"
     *
     * @param string $value Wrap edilecek değer
     * @return string
     */
    abstract public function wrap(string $value): string;

    // ==================== TABLE PREFIX ====================

    /**
     * Tablo adını wrap et (prefix ekleyerek)
     *
     * @param string $table Tablo adı
     * @return string
     */
    public function wrapTable(string $table): string
    {
        // Blueprint instance ise tablo adını al
        if ($table instanceof Blueprint) {
            $table = $table->getTable();
        }

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