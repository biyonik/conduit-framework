<?php

declare(strict_types=1);

namespace Conduit\Database\Relations;

use Conduit\Database\Model;
use Conduit\Database\Collection;
use JsonException;

/**
 * HasMany Relation
 *
 * One-to-Many relationship (Parent has many children)
 *
 * Örnek: User hasMany Posts
 * users table: id
 * posts table: user_id (foreign key)
 *
 * @package Conduit\Database\Relations
 */
class HasMany extends Relation
{
    /**
     * Constructor
     *
     * @param Model $parent Parent model
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key (default: parent_id)
     * @param string|null $localKey Local key (default: id)
     */
    public function __construct(
        Model $parent,
        string $related,
        protected ?string $foreignKey = null,
        protected ?string $localKey = null
    ) {
        parent::__construct($parent, $related);

        $this->foreignKey = $foreignKey ?? $this->getForeignKeyName();
        $this->localKey = $localKey ?? $parent->getKeyName();

        $this->addConstraints();
    }

    /**
     * {@inheritdoc}
     */
    public function addConstraints(): void
    {
        if ($this->parent->exists()) {
            $this->query->where(
                $this->foreignKey,
                $this->parent->getAttribute($this->localKey)
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * Eager loading için constraint ekle
     * WHERE user_id IN (1,2,3,4,5)
     */
    public function addEagerConstraints(Collection $models): void
    {
        // Parent model'lerin key'lerini topla
        $keys = $models->pluck($this->localKey)->all();

        // WHERE IN constraint ekle
        $this->query->whereIn($this->foreignKey, array_values(array_unique($keys)));
    }

    /**
     * {@inheritdoc}
     *
     * Related model'leri parent model'lere eşleştir
     *
     * HasMany için her parent birden fazla related model alabilir
     *
     * @throws JsonException
     */
    public function match(Collection $models, Collection $results, string $relation): Collection
    {
        // Related model'leri foreign key'e göre grupla
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->foreignKey);

            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }

            $dictionary[$key][] = $result;
        }

        // Her parent model için matching related model'leri bul ve attach et
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, new Collection($dictionary[$key]));
            } else {
                $model->setRelation($relation, new Collection([]));
            }
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     */
    public function getResults(): Collection
    {
        return $this->get();
    }

    /**
     * Related model oluştur ve kaydet
     *
     * @param array $attributes
     * @return Model
     * @throws JsonException
     */
    public function create(array $attributes): Model
    {
        $instance = $this->newRelatedInstance($attributes);
        $instance->setAttribute($this->foreignKey, $this->parent->getAttribute($this->localKey));
        $instance->save();

        return $instance;
    }

    /**
     * Birden fazla related model oluştur
     *
     * @param array $records Array of attributes
     * @return Collection
     * @throws JsonException
     */
    public function createMany(array $records): Collection
    {
        $instances = new Collection();

        foreach ($records as $attributes) {
            $instances[] = $this->create($attributes);
        }

        return $instances;
    }

    /**
     * Mevcut model'i relate et
     *
     * @param Model $model
     * @return Model
     * @throws JsonException
     */
    public function save(Model $model): Model
    {
        $model->setAttribute($this->foreignKey, $this->parent->getAttribute($this->localKey));
        $model->save();

        return $model;
    }

    /**
     * Birden fazla model'i relate et
     *
     * @param iterable $models
     * @return iterable
     * @throws JsonException
     */
    public function saveMany(iterable $models): iterable
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    /**
     * İlişkili tüm kayıtları sil
     *
     * @return int Silinen kayıt sayısı
     */
    public function delete(): int
    {
        return $this->query->delete();
    }

    /**
     * Related kayıt sayısını al
     *
     * @return int
     */
    public function count(): int
    {
        return $this->query->count();
    }
}
