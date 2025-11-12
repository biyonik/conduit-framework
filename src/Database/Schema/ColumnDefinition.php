<?php

declare(strict_types=1);

namespace Conduit\Database\Schema;

/**
 * Column Definition - Kolon tanımı ve modifiers
 * 
 * Fluent interface ile kolon özelliklerini zincirleyerek tanımlama:
 * $table->string('email')->nullable()->unique()->default('test@example.com')
 */
class ColumnDefinition
{
    /**
     * Kolon özellikleri
     */
    protected array $attributes = [];

    /**
     * Constructor
     * 
     * @param array $attributes İlk kolon özellikleri
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Kolonu nullable yap (NULL değer alabilir)
     * 
     * @return $this Fluent interface için
     */
    public function nullable(bool $value = true): self
    {
        $this->attributes['nullable'] = $value;
        return $this;
    }

    /**
     * Default değer belirle
     * 
     * @param mixed $value Default değer
     * @return $this
     */
    public function default(mixed $value): self
    {
        $this->attributes['default'] = $value;
        return $this;
    }

    /**
     * Unique constraint ekle
     * 
     * @return $this
     */
    public function unique(string|bool $indexName = true): self
    {
        $this->attributes['unique'] = $indexName;
        return $this;
    }

    /**
     * Index ekle
     * 
     * @return $this
     */
    public function index(string|bool $indexName = true): self
    {
        $this->attributes['index'] = $indexName;
        return $this;
    }

    /**
     * Primary key yap
     * 
     * @return $this
     */
    public function primary(): self
    {
        $this->attributes['primary'] = true;
        return $this;
    }

    /**
     * Unsigned (sadece pozitif sayılar)
     * 
     * @return $this
     */
    public function unsigned(): self
    {
        $this->attributes['unsigned'] = true;
        return $this;
    }

    /**
     * Auto increment
     * 
     * @return $this
     */
    public function autoIncrement(): self
    {
        $this->attributes['autoIncrement'] = true;
        return $this;
    }

    /**
     * Kolon açıklaması (comment)
     * 
     * @param string $comment Açıklama metni
     * @return $this
     */
    public function comment(string $comment): self
    {
        $this->attributes['comment'] = $comment;
        return $this;
    }

    /**
     * Kolonun hangi kolondan sonra ekleneceğini belirt (ALTER TABLE için)
     * 
     * @param string $column Kolon adı
     * @return $this
     */
    public function after(string $column): self
    {
        $this->attributes['after'] = $column;
        return $this;
    }

    /**
     * Kolonun en başa eklenmesini belirt (ALTER TABLE için)
     * 
     * @return $this
     */
    public function first(): self
    {
        $this->attributes['first'] = true;
        return $this;
    }

    /**
     * Charset belirle (MySQL için)
     * 
     * @param string $charset Charset (utf8mb4, etc.)
     * @return $this
     */
    public function charset(string $charset): self
    {
        $this->attributes['charset'] = $charset;
        return $this;
    }

    /**
     * Collation belirle (MySQL için)
     * 
     * @param string $collation Collation (utf8mb4_unicode_ci, etc.)
     * @return $this
     */
    public function collation(string $collation): self
    {
        $this->attributes['collation'] = $collation;
        return $this;
    }

    /**
     * Get all attributes
     * 
     * @return array Tüm kolon özellikleri
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get a specific attribute
     * 
     * @param string $key Özellik adı
     * @param mixed $default Default değer
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Set an attribute
     * 
     * @param string $key Özellik adı
     * @param mixed $value Değer
     * @return $this
     */
    public function set(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }
}