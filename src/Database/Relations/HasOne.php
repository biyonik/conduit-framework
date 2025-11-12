<?php

declare(strict_types=1);

namespace Conduit\Database\Relations;

use Conduit\Database\Collection;
use Conduit\Database\Model;
use JsonException;

/**
 * HasOne Relation
 *
 * One-to-One relationship (Parent has one child)
 *
 * Örnek: User hasOne Profile
 * users table: id
 * profiles table: user_id (foreign key)
 *
 * @package Conduit\Database\Relations
 */
class HasOne extends Relation
{
    /**
     * Constructor
     *
     * @param Model $parent Parent model
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key (default: parent_id)
     * @param string|null $localKey Local key (default: id)
     * @throws JsonException
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
     * @throws JsonException
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
     * @throws JsonException
     */
    public function match(Collection $models, Collection $results, string $relation): Collection
    {
        // Related model'leri foreign key'e göre dictionary'ye çevir
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->foreignKey);
            $dictionary[$key] = $result;
        }

        // Her parent model için matching related model'i bul ve attach et
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     */
    public function getResults(): ?Model
    {
        return $this->first();
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
}
