<?php

declare(strict_types=1);

namespace Conduit\Database\Schema;

/**
 * Foreign Key Definition - Foreign key tanımı ve constraint'ler
 * 
 * Fluent interface ile foreign key constraint'leri zincirleyerek tanımlama:
 * $table->foreignId('user_id')->constrained()->onDelete('cascade')
 */
class ForeignKeyDefinition
{
    /**
     * Blueprint instance
     */
    protected Blueprint $blueprint;

    /**
     * Foreign key kolonu/kolonları
     */
    protected array $columns;

    /**
     * Constraint adı
     */
    protected ?string $name;

    /**
     * Referenced tablo
     */
    protected ?string $references = null;

    /**
     * Referenced kolon(lar)
     */
    protected ?array $on = null;

    /**
     * ON DELETE action
     */
    protected ?string $onDelete = null;

    /**
     * ON UPDATE action
     */
    protected ?string $onUpdate = null;

    /**
     * Constructor
     * 
     * @param Blueprint $blueprint Blueprint instance
     * @param string|array $columns Foreign key kolonu/kolonları
     * @param string|null $name Constraint adı
     */
    public function __construct(Blueprint $blueprint, string|array $columns, ?string $name = null)
    {
        $this->blueprint = $blueprint;
        $this->columns = (array) $columns;
        $this->name = $name;
    }

    /**
     * Set the referenced table and column (automatic)
     * 
     * Kolon adından otomatik olarak referenced table ve column'u çıkarır.
     * Örn: 'user_id' -> references 'id' on 'users'
     * 
     * @param string|null $table Referenced tablo (null ise otomatik çıkarılır)
     * @param string $column Referenced kolon (default: 'id')
     * @return $this
     * 
     * @example
     * $table->foreignId('user_id')->constrained(); 
     * // References users(id)
     * 
     * $table->foreignId('author_id')->constrained('users');
     * // References users(id)
     */
    public function constrained(?string $table = null, string $column = 'id'): self
    {
        // Eğer tablo belirtilmemişse, kolon adından çıkar
        if ($table === null && count($this->columns) === 1) {
            $columnName = $this->columns[0];
            
            // 'user_id' -> 'users', 'post_id' -> 'posts'
            if (preg_match('/^(.+)_id$/', $columnName, $matches)) {
                $table = $matches[1] . 's'; // Pluralize (basit)
            } else {
                throw new \InvalidArgumentException(
                    "Cannot automatically determine referenced table from column '{$columnName}'. " .
                    "Please specify the table explicitly: constrained('table_name')"
                );
            }
        }

        return $this->references($column)->on($table);
    }

    /**
     * Set the referenced column(s)
     * 
     * @param string|array $columns Referenced kolon(lar)
     * @return $this
     */
    public function references(string|array $columns): self
    {
        $this->references = implode(',', (array) $columns);
        return $this;
    }

    /**
     * Set the referenced table
     * 
     * @param string $table Referenced tablo adı
     * @return $this
     */
    public function on(string $table): self
    {
        $this->on = [$table];
        $this->addCommand();
        return $this;
    }

    /**
     * Set the ON DELETE action
     * 
     * @param string $action Action (cascade, set null, restrict, no action)
     * @return $this
     * 
     * @example ->onDelete('cascade')
     * @example ->onDelete('set null')
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    /**
     * Set the ON UPDATE action
     * 
     * @param string $action Action (cascade, set null, restrict, no action)
     * @return $this
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }

    /**
     * Shortcut for onDelete('cascade')
     * 
     * @return $this
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('cascade');
    }

    /**
     * Shortcut for onUpdate('cascade')
     * 
     * @return $this
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('cascade');
    }

    /**
     * Shortcut for onDelete('set null')
     * 
     * @return $this
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('set null');
    }

    /**
     * Shortcut for onDelete('restrict')
     * 
     * @return $this
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete('restrict');
    }

    /**
     * Add the foreign key command to blueprint
     */
    protected function addCommand(): void
    {
        $this->blueprint->addCommand('foreign', [
            'columns' => $this->columns,
            'index' => $this->name,
            'references' => $this->references,
            'on' => $this->on[0] ?? null,
            'onDelete' => $this->onDelete,
            'onUpdate' => $this->onUpdate,
        ]);
    }
}