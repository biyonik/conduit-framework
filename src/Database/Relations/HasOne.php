<?php

declare(strict_types=1);

namespace Conduit\Database\Relations;

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
