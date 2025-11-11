<?php

declare(strict_types=1);

namespace Conduit\Database\Relations;

use Conduit\Database\Model;
use Conduit\Database\QueryBuilder;
use Conduit\Database\Collection;

/**
 * Base Relation Class
 *
 * Tüm relationship türleri bu sınıftan türer.
 *
 * @package Conduit\Database\Relations
 */
abstract class Relation
{
    /**
     * Related model query builder
     */
    protected QueryBuilder $query;

    /**
     * Constructor
     *
     * @param Model $parent Parent model instance
     * @param string $related Related model class name
     */
    public function __construct(
        protected Model $parent,
        protected string $related
    ) {
        $this->query = $this->getRelatedQuery();
    }

    /**
     * Related model için query builder al
     *
     * @return QueryBuilder
     */
    protected function getRelatedQuery(): QueryBuilder
    {
        return (new $this->related())->newQuery();
    }

    /**
     * Relationship constraint'lerini ekle
     *
     * Her relationship türü kendi constraint'lerini implement eder.
     *
     * @return void
     */
    abstract public function addConstraints(): void;

    /**
     * Relationship sonuçlarını getir
     *
     * @return mixed Model, Collection veya null
     */
    abstract public function getResults(): mixed;

    /**
     * Foreign key kolon adını al
     *
     * Default: parent_table_id
     * Örn: user_id, post_id
     *
     * @return string
     */
    protected function getForeignKeyName(): string
    {
        return strtolower(class_basename($this->parent)) . '_id';
    }

    /**
     * Related model'in primary key'ini al
     *
     * @return string
     */
    protected function getRelatedKeyName(): string
    {
        return (new $this->related())->getKeyName();
    }

    /**
     * Query builder'a where constraint ekle
     *
     * @param string $column Kolon
     * @param mixed $value Değer
     * @return self
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        $this->query->where($column, $operator, $value);
        return $this;
    }

    /**
     * Query builder'a order by ekle
     *
     * @param string $column Kolon
     * @param string $direction Direction
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    /**
     * İlişkili kayıtları getir
     *
     * @return Collection
     */
    public function get(): Collection
    {
        return $this->query->get();
    }

    /**
     * İlk ilişkili kaydı getir
     *
     * @return Model|null
     */
    public function first(): ?Model
    {
        $result = $this->query->first();

        if ($result === null) {
            return null;
        }

        return (new $this->related())->newFromArray($result);
    }

    /**
     * Array'den yeni model oluştur
     *
     * @param array $attributes
     * @return Model
     */
    protected function newRelatedInstance(array $attributes = []): Model
    {
        return new $this->related($attributes);
    }

    /**
     * Query builder'ı dön (method chaining için)
     *
     * @return QueryBuilder
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Magic call - query builder metodlarını proxy et
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->$method(...$parameters);

        // Eğer query builder dönüyorsa, self dön (fluent)
        if ($result instanceof QueryBuilder) {
            return $this;
        }

        return $result;
    }
}
