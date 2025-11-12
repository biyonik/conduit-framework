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
