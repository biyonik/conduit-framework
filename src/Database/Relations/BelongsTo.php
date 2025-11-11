<?php

declare(strict_types=1);

namespace Conduit\Database\Relations;

use Conduit\Database\Model;
use JsonException;

/**
 * BelongsTo Relation
 *
 * Inverse of HasOne/HasMany (Child belongs to Parent)
 *
 * Örnek: Post belongsTo User
 * posts table: user_id (foreign key)
 * users table: id
 *
 * @package Conduit\Database\Relations
 */
class BelongsTo extends Relation
{
    /**
     * Constructor
     *
     * @param Model $parent Child model (Post)
     * @param string $related Parent model class (User)
     * @param string|null $foreignKey Foreign key on child (user_id)
     * @param string|null $ownerKey Primary key on parent (id)
     * @throws JsonException
     */
    public function __construct(
        Model $parent,
        string $related,
        protected ?string $foreignKey = null,
        protected ?string $ownerKey = null
    ) {
        parent::__construct($parent, $related);

        $this->foreignKey = $foreignKey ?? strtolower(class_basename($related)) . '_id';
        $this->ownerKey = $ownerKey ?? (new $related())->getKeyName();

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
                $this->ownerKey,
                $this->parent->getAttribute($this->foreignKey)
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
     * Parent model'i child'a associate et
     *
     * @param Model $model Parent model
     * @return Model Child model (updated)
     * @throws JsonException
     */
    public function associate(Model $model): Model
    {
        $this->parent->setAttribute(
            $this->foreignKey,
            $model->getAttribute($this->ownerKey)
        );

        return $this->parent;
    }

    /**
     * Parent model'den child'ı dissociate et
     *
     * @return Model Child model (updated)
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);

        return $this->parent;
    }
}
