<?php

declare(strict_types=1);

namespace Conduit\Database\Relations;

use Conduit\Database\Collection;
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
     *
     * BelongsTo için eager constraint
     * WHERE id IN (1,2,3,4,5) -- parent ID'leri
     */
    public function addEagerConstraints(Collection $models): void
    {
        // Child model'lerin foreign key değerlerini topla
        $keys = $models->pluck($this->foreignKey)->all();

        // WHERE IN constraint ekle (owner key'e göre)
        $this->query->whereIn($this->ownerKey, array_values(array_unique(array_filter($keys))));
    }

    /**
     * {@inheritdoc}
     *
     * Parent model'leri child model'lere eşleştir
     *
     * @throws JsonException
     */
    public function match(Collection $models, Collection $results, string $relation): Collection
    {
        // Parent model'leri owner key'e göre dictionary'ye çevir
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->ownerKey);
            $dictionary[$key] = $result;
        }

        // Her child model için matching parent model'i bul ve attach et
        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);

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
