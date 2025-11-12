<?php

declare(strict_types=1);

namespace Conduit\Database\Schema;

use Conduit\Database\Connection;
use Conduit\Database\Grammar\Grammar;

/**
 * Blueprint - Tablo tanımlama DSL
 * 
 * Fluent interface ile tablo kolonlarını ve constraint'leri tanımlama.
 * Laravel'ın Schema Builder'ına benzer syntax.
 */
class Blueprint
{
    /**
     * Tablo adı
     */
    protected string $table;

    /**
     * Modify mode (ALTER TABLE) mi yoksa CREATE TABLE mi?
     */
    protected bool $modify;

    /**
     * Tanımlanan kolonlar
     * 
     * @var array<ColumnDefinition>
     */
    protected array $columns = [];

    /**
     * Tanımlanan command'lar (index, foreign key, etc.)
     */
    protected array $commands = [];

    /**
     * Constructor
     * 
     * @param string $table Tablo adı
     * @param bool $modify Modify mode mu? (ALTER TABLE)
     */
    public function __construct(string $table, bool $modify = false)
    {
        $this->table = $table;
        $this->modify = $modify;
    }

    /**
     * Get table name
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get columns
     * 
     * @return array<ColumnDefinition>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get commands
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Is this a modify (ALTER TABLE) blueprint?
     */
    public function isModify(): bool
    {
        return $this->modify;
    }

    /**
     * Add a column to the blueprint
     * 
     * @param string $type Kolon tipi (integer, string, etc.)
     * @param string $name Kolon adı
     * @param array $parameters Ekstra parametreler (length, precision, etc.)
     * @return ColumnDefinition Fluent interface için
     */
    protected function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition(array_merge([
            'type' => $type,
            'name' => $name,
        ], $parameters));

        $this->columns[] = $column;

        return $column;
    }

    /**
     * Add a command to the blueprint
     * 
     * @param string $name Command adı (primary, unique, index, foreign, etc.)
     * @param array $parameters Command parametreleri
     */
    public function addCommand(string $name, array $parameters = []): void
    {
        $this->commands[] = array_merge(['name' => $name], $parameters);
    }

    // ==================== PRIMARY KEY COLUMNS ====================

    /**
     * Create an auto-incrementing BIGINT (unsigned) primary key
     * 
     * @param string $column Kolon adı (default: 'id')
     * @return ColumnDefinition
     * 
     * @example $table->id(); // id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     */
    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column);
    }

    /**
     * Create an auto-incrementing BIGINT (unsigned)
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn('bigIncrements', $column)
            ->unsigned()
            ->autoIncrement()
            ->primary();
    }

    /**
     * Create an auto-incrementing INT (unsigned)
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function increments(string $column): ColumnDefinition
    {
        return $this->addColumn('increments', $column)
            ->unsigned()
            ->autoIncrement()
            ->primary();
    }

    /**
     * Create a UUID column
     * 
     * @param string $column Kolon adı (default: 'uuid')
     * @return ColumnDefinition
     * 
     * @example $table->uuid('uuid')->primary();
     */
    public function uuid(string $column = 'uuid'): ColumnDefinition
    {
        return $this->addColumn('uuid', $column);
    }

    // ==================== STRING COLUMNS ====================

    /**
     * Create a VARCHAR column
     * 
     * @param string $column Kolon adı
     * @param int $length Maksimum uzunluk (default: 255)
     * @return ColumnDefinition
     * 
     * @example $table->string('name', 100);
     */
    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column, ['length' => $length]);
    }

    /**
     * Create a CHAR column (fixed length)
     * 
     * @param string $column Kolon adı
     * @param int $length Uzunluk (default: 255)
     * @return ColumnDefinition
     */
    public function char(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('char', $column, ['length' => $length]);
    }

    /**
     * Create a TEXT column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a MEDIUMTEXT column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Create a LONGTEXT column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    // ==================== NUMERIC COLUMNS ====================

    /**
     * Create an INTEGER column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column);
    }

    /**
     * Create a TINYINT column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function tinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column);
    }

    /**
     * Create a SMALLINT column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function smallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column);
    }

    /**
     * Create a MEDIUMINT column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function mediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumInteger', $column);
    }

    /**
     * Create a BIGINT column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column);
    }

    /**
     * Create an unsigned INTEGER column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->integer($column)->unsigned();
    }

    /**
     * Create an unsigned BIGINT column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->bigInteger($column)->unsigned();
    }

    /**
     * Create a FLOAT column
     * 
     * @param string $column Kolon adı
     * @param int $total Toplam basamak sayısı
     * @param int $places Ondalık basamak sayısı
     * @return ColumnDefinition
     */
    public function float(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn('float', $column, compact('total', 'places'));
    }

    /**
     * Create a DOUBLE column
     * 
     * @param string $column Kolon adı
     * @param int|null $total Toplam basamak sayısı
     * @param int|null $places Ondalık basamak sayısı
     * @return ColumnDefinition
     */
    public function double(string $column, ?int $total = null, ?int $places = null): ColumnDefinition
    {
        return $this->addColumn('double', $column, compact('total', 'places'));
    }

    /**
     * Create a DECIMAL column
     * 
     * @param string $column Kolon adı
     * @param int $total Toplam basamak sayısı (default: 8)
     * @param int $places Ondalık basamak sayısı (default: 2)
     * @return ColumnDefinition
     * 
     * @example $table->decimal('price', 8, 2); // DECIMAL(8,2)
     */
    public function decimal(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, compact('total', 'places'));
    }

    /**
     * Create a BOOLEAN column (TINYINT(1))
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    // ==================== DATE/TIME COLUMNS ====================

    /**
     * Create a DATE column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a DATETIME column
     * 
     * @param string $column Kolon adı
     * @param int $precision Microsecond precision (default: 0)
     * @return ColumnDefinition
     */
    public function datetime(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('datetime', $column, compact('precision'));
    }

    /**
     * Create a TIMESTAMP column
     * 
     * @param string $column Kolon adı
     * @param int $precision Microsecond precision (default: 0)
     * @return ColumnDefinition
     */
    public function timestamp(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    /**
     * Create a TIME column
     * 
     * @param string $column Kolon adı
     * @param int $precision Microsecond precision (default: 0)
     * @return ColumnDefinition
     */
    public function time(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    /**
     * Create a YEAR column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function year(string $column): ColumnDefinition
    {
        return $this->addColumn('year', $column);
    }

    // ==================== SPECIAL COLUMNS ====================

    /**
     * Create a JSON column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a JSONB column (PostgreSQL)
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function jsonb(string $column): ColumnDefinition
    {
        return $this->addColumn('jsonb', $column);
    }

    /**
     * Create a BINARY column
     * 
     * @param string $column Kolon adı
     * @return ColumnDefinition
     */
    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create an ENUM column
     * 
     * @param string $column Kolon adı
     * @param array $allowed İzin verilen değerler
     * @return ColumnDefinition
     * 
     * @example $table->enum('status', ['pending', 'active', 'inactive']);
     */
    public function enum(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    /**
     * Create a SET column (MySQL)
     * 
     * @param string $column Kolon adı
     * @param array $allowed İzin verilen değerler
     * @return ColumnDefinition
     */
    public function set(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('set', $column, compact('allowed'));
    }

    // ==================== FOREIGN KEY COLUMNS ====================

    /**
     * Create an unsigned BIGINT column for foreign key
     * 
     * @param string $column Kolon adı (genelde 'user_id' gibi)
     * @return ForeignKeyDefinition
     * 
     * @example
     * $table->foreignId('user_id')
     *       ->constrained()
     *       ->onDelete('cascade');
     */
    public function foreignId(string $column): ForeignKeyDefinition
    {
        // Foreign key kolonu ekle
        $this->unsignedBigInteger($column);

        // ForeignKeyDefinition döndür (fluent interface)
        return new ForeignKeyDefinition($this, $column);
    }

    /**
     * Create a foreign key constraint
     * 
     * @param string|array $columns Kolon(lar)
     * @param string|null $name Foreign key constraint adı
     * @return ForeignKeyDefinition
     */
    public function foreign(string|array $columns, ?string $name = null): ForeignKeyDefinition
    {
        $columns = (array) $columns;
        return new ForeignKeyDefinition($this, $columns, $name);
    }

    // ==================== INDEXES ====================

    /**
     * Add a primary key
     * 
     * @param string|array $columns Kolon(lar)
     * @param string|null $indexName Index adı
     * @return void
     * 
     * @example $table->primary('id');
     * @example $table->primary(['user_id', 'post_id']);
     */
    public function primary(string|array $columns, ?string $indexName = null): void
    {
        $columns = (array) $columns;
        
        $this->addCommand('primary', [
            'columns' => $columns,
            'index' => $indexName,
        ]);
    }

    /**
     * Add a unique index
     * 
     * @param string|array $columns Kolon(lar)
     * @param string|null $indexName Index adı
     * @return void
     * 
     * @example $table->unique('email');
     * @example $table->unique(['email', 'username'], 'unique_email_username');
     */
    public function unique(string|array $columns, ?string $indexName = null): void
    {
        $columns = (array) $columns;
        
        $this->addCommand('unique', [
            'columns' => $columns,
            'index' => $indexName,
        ]);
    }

    /**
     * Add a regular index
     * 
     * @param string|array $columns Kolon(lar)
     * @param string|null $indexName Index adı
     * @return void
     * 
     * @example $table->index('email');
     * @example $table->index(['created_at', 'status'], 'idx_created_status');
     */
    public function index(string|array $columns, ?string $indexName = null): void
    {
        $columns = (array) $columns;
        
        $this->addCommand('index', [
            'columns' => $columns,
            'index' => $indexName,
        ]);
    }

    /**
     * Add a fulltext index (MySQL)
     * 
     * @param string|array $columns Kolon(lar)
     * @param string|null $indexName Index adı
     * @return void
     */
    public function fulltext(string|array $columns, ?string $indexName = null): void
    {
        $columns = (array) $columns;
        
        $this->addCommand('fulltext', [
            'columns' => $columns,
            'index' => $indexName,
        ]);
    }

    /**
     * Add a spatial index (MySQL)
     * 
     * @param string|array $columns Kolon(lar)
     * @param string|null $indexName Index adı
     * @return void
     */
    public function spatialIndex(string|array $columns, ?string $indexName = null): void
    {
        $columns = (array) $columns;
        
        $this->addCommand('spatialIndex', [
            'columns' => $columns,
            'index' => $indexName,
        ]);
    }

    // ==================== DROP OPERATIONS (for ALTER TABLE) ====================

    /**
     * Drop a column
     * 
     * @param string|array $columns Kolon(lar)
     * @return void
     */
    public function dropColumn(string|array $columns): void
    {
        $columns = (array) $columns;
        
        $this->addCommand('dropColumn', ['columns' => $columns]);
    }

    /**
     * Drop a primary key
     * 
     * @param string|array|null $index Index adı veya kolon(lar)
     * @return void
     */
    public function dropPrimary(string|array|null $index = null): void
    {
        $this->addCommand('dropPrimary', ['index' => $index]);
    }

    /**
     * Drop a unique index
     * 
     * @param string|array $index Index adı veya kolon(lar)
     * @return void
     */
    public function dropUnique(string|array $index): void
    {
        $this->addCommand('dropUnique', ['index' => $index]);
    }

    /**
     * Drop a regular index
     * 
     * @param string|array $index Index adı veya kolon(lar)
     * @return void
     */
    public function dropIndex(string|array $index): void
    {
        $this->addCommand('dropIndex', ['index' => $index]);
    }

    /**
     * Drop a foreign key
     * 
     * @param string|array $index Foreign key constraint adı veya kolon(lar)
     * @return void
     */
    public function dropForeign(string|array $index): void
    {
        $this->addCommand('dropForeign', ['index' => $index]);
    }

    // ==================== COLUMN MODIFICATIONS ====================

    /**
     * Rename a column
     * 
     * @param string $from Eski kolon adı
     * @param string $to Yeni kolon adı
     * @return void
     */
    public function renameColumn(string $from, string $to): void
    {
        $this->addCommand('renameColumn', ['from' => $from, 'to' => $to]);
    }

    /**
     * Rename the table
     * 
     * @param string $to Yeni tablo adı
     * @return void
     */
    public function rename(string $to): void
    {
        $this->addCommand('rename', ['to' => $to]);
    }

    // ==================== SHORTCUTS ====================

    /**
     * Add created_at and updated_at timestamp columns
     * 
     * @param int $precision Microsecond precision (default: 0)
     * @return void
     * 
     * @example $table->timestamps();
     */
    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Add created_at and updated_at nullable timestamp columns with defaults
     * 
     * @param int $precision Microsecond precision (default: 0)
     * @return void
     */
    public function nullableTimestamps(int $precision = 0): void
    {
        $this->timestamps($precision);
    }

    /**
     * Add a deleted_at timestamp column for soft deletes
     * 
     * @param string $column Kolon adı (default: 'deleted_at')
     * @param int $precision Microsecond precision (default: 0)
     * @return ColumnDefinition
     * 
     * @example $table->softDeletes();
     */
    public function softDeletes(string $column = 'deleted_at', int $precision = 0): ColumnDefinition
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    /**
     * Add a remember_token column for "remember me" tokens
     * 
     * @return ColumnDefinition
     * 
     * @example $table->rememberToken();
     */
    public function rememberToken(): ColumnDefinition
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * Add morphs columns (for polymorphic relationships)
     * 
     * @param string $name Morph adı (örn: 'taggable')
     * @param string|null $indexName Index adı
     * @return void
     * 
     * @example $table->morphs('taggable');
     * // Creates: taggable_type VARCHAR(255), taggable_id BIGINT UNSIGNED
     * // With index on (taggable_type, taggable_id)
     */
    public function morphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->unsignedBigInteger("{$name}_id");
        
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add nullable morphs columns
     * 
     * @param string $name Morph adı
     * @param string|null $indexName Index adı
     * @return void
     */
    public function nullableMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();
        $this->unsignedBigInteger("{$name}_id")->nullable();
        
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    // ==================== SQL GENERATION ====================

    /**
     * Get the SQL statements to create/alter the table
     * 
     * @param Grammar $grammar SQL grammar
     * @param Connection $connection Database connection
     * @return array<string> SQL statements
     */
    public function toSql(Grammar $grammar, Connection $connection): array
    {
        $statements = [];

        if ($this->modify) {
            // ALTER TABLE mode
            $statements = $this->getAlterTableStatements($grammar, $connection);
        } else {
            // CREATE TABLE mode
            $statements[] = $this->getCreateTableStatement($grammar);
        }

        // Index ve foreign key statement'ları ekle
        foreach ($this->getCommandStatements($grammar, $connection) as $statement) {
            $statements[] = $statement;
        }

        return array_filter($statements);
    }

    /**
     * Get the CREATE TABLE statement
     * 
     * @param Grammar $grammar SQL grammar
     * @return string CREATE TABLE SQL
     */
    protected function getCreateTableStatement(Grammar $grammar): string
    {
        return $grammar->compileCreateTable($this);
    }

    /**
     * Get ALTER TABLE statements
     * 
     * @param Grammar $grammar SQL grammar
     * @param Connection $connection Database connection
     * @return array<string> ALTER TABLE SQL statements
     */
    protected function getAlterTableStatements(Grammar $grammar, Connection $connection): array
    {
        $statements = [];

        // Her kolon için ALTER TABLE
        foreach ($this->columns as $column) {
            $statements[] = $grammar->compileAddColumn($this, $column);
        }

        return $statements;
    }

    /**
     * Get command (index, foreign key, etc.) statements
     * 
     * @param Grammar $grammar SQL grammar
     * @param Connection $connection Database connection
     * @return array<string> Command SQL statements
     */
    protected function getCommandStatements(Grammar $grammar, Connection $connection): array
    {
        $statements = [];

        foreach ($this->commands as $command) {
            $method = 'compile' . ucfirst($command['name']);

            if (method_exists($grammar, $method)) {
                $sql = $grammar->$method($this, $command);
                
                if ($sql !== null) {
                    $statements[] = $sql;
                }
            }
        }

        return $statements;
    }
}