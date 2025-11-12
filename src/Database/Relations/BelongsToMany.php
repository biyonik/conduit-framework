<?php

declare(strict_types=1);

namespace Conduit\Database\Relations;

use Conduit\Database\Collection;
use Conduit\Database\Model;
use JsonException;

/**
 * BelongsToMany Relation
 *
 * Many-to-Many relationship (via pivot table)
 *
 * Örnek: User belongsToMany Role
 * users table: id
 * roles table: id
 * user_roles table: user_id, role_id (pivot)
 *
 * @package Conduit\Database\Relations
 */
class BelongsToMany extends Relation
{
    /**
     * Constructor
     *
     * @param Model $parent Parent model (User)
     * @param string $related Related model class (Role)
     * @param string|null $pivotTable Pivot table name (user_roles)
     * @param string|null $foreignPivotKey Foreign key on pivot (user_id)
     * @param string|null $relatedPivotKey Related key on pivot (role_id)
     * @param string|null $parentKey Parent model key (id)
     * @param string|null $relatedKey Related model key (id)
     */
    public function __construct(
        Model $parent,
        string $related,
        protected ?string $pivotTable = null,
        protected ?string $foreignPivotKey = null,
        protected ?string $relatedPivotKey = null,
        protected ?string $parentKey = null,
        protected ?string $relatedKey = null
    ) {
        parent::__construct($parent, $related);

        // Default values
        $this->pivotTable = $pivotTable ?? $this->getPivotTableName();
        $this->foreignPivotKey = $foreignPivotKey ?? strtolower(class_basename($parent)) . '_id';
        $this->relatedPivotKey = $relatedPivotKey ?? strtolower(class_basename($related)) . '_id';
        $this->parentKey = $parentKey ?? $parent->getKeyName();
        $this->relatedKey = $relatedKey ?? (new $related())->getKeyName();

        $this->addConstraints();
    }

    /**
     * Pivot table name oluştur (alphabetically ordered)
     *
     * Örn: User + Role = role_user (alphabetical)
     *
     * @return string
     */
    protected function getPivotTableName(): string
    {
        $segments = [
            strtolower(class_basename($this->parent)),
            strtolower(class_basename($this->related))
        ];

        sort($segments);

        return implode('_', $segments);
    }

    /**
     * {@inheritdoc}
     */
    public function addConstraints(): void
    {
        if ($this->parent->exists()) {
            // JOIN pivot table
            $this->query->join(
                $this->pivotTable,
                $this->related . '.' . $this->relatedKey,
                '=',
                $this->pivotTable . '.' . $this->relatedPivotKey
            );

            // WHERE pivot.parent_id = ?
            $this->query->where(
                $this->pivotTable . '.' . $this->foreignPivotKey,
                $this->parent->getAttribute($this->parentKey)
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
     * {@inheritdoc}
     *
     * BelongsToMany için eager constraint (pivot table kullanarak)
     */
    public function addEagerConstraints(Collection $models): void
    {
        // Parent model'lerin key'lerini topla
        $keys = $models->pluck($this->parentKey)->all();

        // Pivot table üzerinden WHERE IN
        $this->query->whereIn(
            $this->pivotTable . '.' . $this->foreignPivotKey,
            array_values(array_unique($keys))
        );
    }

    /**
     * {@inheritdoc}
     *
     * Many-to-many relationship'leri eşleştir
     */
    public function match(Collection $models, Collection $results, string $relation): Collection
    {
        // Related model'leri pivot key'e göre grupla
        $dictionary = $this->buildDictionary($results);

        // Her parent model için matching related model'leri bul
        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, new Collection($dictionary[$key]));
            } else {
                $model->setRelation($relation, new Collection([]));
            }
        }

        return $models;
    }

    /**
     * Pivot sonuçlarından dictionary oluştur
     *
     * @param Collection $results
     * @return array
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        // Pivot table'dan sonuçları grupla
        foreach ($results as $result) {
            // Pivot table'dan foreign key'i al
            $foreignKey = $result->getAttribute($this->foreignPivotKey);

            if (!isset($dictionary[$foreignKey])) {
                $dictionary[$foreignKey] = [];
            }

            $dictionary[$foreignKey][] = $result;
        }

        return $dictionary;
    }

    /**
     * Related model(s) attach et (pivot table'a ekle)
     *
     * @param int|array $ids Related model ID(s)
     * @return void
     * @throws JsonException
     */
    public function attach(int|array $ids): void
    {
        $ids = is_array($ids) ? $ids : [$ids];

        foreach ($ids as $id) {
            $this->parent->newQuery()
                ->insert(
                    $this->grammar->compileInsert($this->pivotTable, [
                        $this->foreignPivotKey,
                        $this->relatedPivotKey
                    ]),
                    [
                        $this->parent->getAttribute($this->parentKey),
                        $id
                    ]
                );
        }
    }

    /**
     * Related model(s) detach et (pivot table'dan sil)
     *
     * @param int|array|null $ids Related model ID(s) (null = tümü)
     * @return int Silinen kayıt sayısı
     */
    public function detach(int|array|null $ids = null): int
    {
        $query = $this->parent->newQuery()
            ->from($this->pivotTable)
            ->where(
                $this->foreignPivotKey,
                $this->parent->getAttribute($this->parentKey)
            );

        // Eğer ID'ler belirtilmişse, sadece onları sil
        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * Sync - pivot table'ı verilen ID'lerle senkronize et
     *
     * Mevcut olmayan ID'leri ekle, fazla olanları sil.
     *
     * @param array $ids Related model IDs
     * @return void
     */
    public function sync(array $ids): void
    {
        // Mevcut ID'leri al
        $current = $this->get()->pluck($this->relatedKey)->toArray();

        // Attach edilecekler (yeni olanlar)
        $attachIds = array_diff($ids, $current);
        if (!empty($attachIds)) {
            $this->attach($attachIds);
        }

        // Detach edilecekler (silinecek olanlar)
        $detachIds = array_diff($current, $ids);
        if (!empty($detachIds)) {
            $this->detach($detachIds);
        }
    }

    /**
     * Toggle - ID varsa sil, yoksa ekle
     *
     * @param int|array $ids Related model ID(s)
     * @return void
     */
    public function toggle(int|array $ids): void
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $current = $this->get()->pluck($this->relatedKey)->toArray();

        foreach ($ids as $id) {
            if (in_array($id, $current, true)) {
                $this->detach($id);
            } else {
                $this->attach($id);
            }
        }
    }
}
