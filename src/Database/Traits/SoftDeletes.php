<?php

declare(strict_types=1);

namespace Conduit\Database\Traits;

use Conduit\Database\QueryBuilder;
use DateTime;
use Exception;

/**
 * SoftDeletes Trait
 *
 * Model'e soft delete özelliği ekler.
 * delete() çağrıldığında kayıt database'den silinmez,
 * sadece deleted_at timestamp'i set edilir.
 *
 * Soft deleted kayıtlar query'lerde otomatik filtrelenir.
 *
 * @package Conduit\Database\Traits
 */
trait SoftDeletes
{
    /**
     * Soft delete aktif mi?
     *
     * @return bool
     */
    public function usesSoftDeletes(): bool
    {
        return $this->softDeletes ?? true;
    }

    /**
     * Deleted at kolon adı
     *
     * @return string
     */
    public function getDeletedAtColumn(): string
    {
        return $this->deletedAtColumn ?? 'deleted_at';
    }

    /**
     * Soft delete yap
     *
     * @return bool
     */
    protected function performSoftDelete(): bool
    {
        $this->setAttribute($this->getDeletedAtColumn(), $this->freshTimestamp());

        // Fire restoring event
        $this->fireModelEvent('deleting');

        $result = $this->save();

        // Fire restored event
        $this->fireModelEvent('deleted');

        return $result;
    }

    /**
     * Force delete - gerçek silme yap
     *
     * Soft delete'i bypass eder ve kaydı database'den siler.
     *
     * @return bool
     */
    public function forceDelete(): bool
    {
        $this->fireModelEvent('deleting');

        $deleted = $this->newQuery()
            ->where($this->getKeyName(), $this->getKey())
            ->delete();

        $this->exists = false;

        $this->fireModelEvent('deleted');

        return $deleted > 0;
    }

    /**
     * Soft deleted model'i restore et
     *
     * @return bool
     */
    public function restore(): bool
    {
        if (!$this->trashed()) {
            return false;
        }

        // Fire restoring event
        $this->fireModelEvent('restoring');

        $this->setAttribute($this->getDeletedAtColumn(), null);
        $result = $this->save();

        // Fire restored event
        $this->fireModelEvent('restored');

        return $result;
    }

    /**
     * Model soft deleted mi kontrol et
     *
     * @return bool
     */
    public function trashed(): bool
    {
        return $this->getAttribute($this->getDeletedAtColumn()) !== null;
    }

    /**
     * Query scope: Soft deleted kayıtları dahil et
     *
     * @param QueryBuilder $query
     * @return QueryBuilder
     */
    public function scopeWithTrashed(QueryBuilder $query): QueryBuilder
    {
        // Soft delete filter'ı kaldır (tüm kayıtları getir)
        return $query;
    }

    /**
     * Query scope: Sadece soft deleted kayıtları getir
     *
     * @param QueryBuilder $query
     * @return QueryBuilder
     */
    public function scopeOnlyTrashed(QueryBuilder $query): QueryBuilder
    {
        return $query->whereNotNull($this->getDeletedAtColumn());
    }

    /**
     * Query scope: Soft deleted kayıtları filtrele (default)
     *
     * @param QueryBuilder $query
     * @return QueryBuilder
     */
    public function scopeWithoutTrashed(QueryBuilder $query): QueryBuilder
    {
        return $query->whereNull($this->getDeletedAtColumn());
    }

    /**
     * Yeni query builder oluştur (soft delete filter ile)
     *
     * @return QueryBuilder
     */
    protected function newQuery(): QueryBuilder
    {
        $builder = parent::newQuery();

        // Soft delete filter ekle (deleted_at IS NULL)
        if ($this->usesSoftDeletes()) {
            $builder->whereNull($this->getDeletedAtColumn());
        }

        return $builder;
    }

    /**
     * Static method: Trashed kayıtları dahil et
     *
     * @return QueryBuilder
     */
    public static function withTrashed(): QueryBuilder
    {
        $instance = new static();

        // Filter olmadan query builder dön
        $builder = new QueryBuilder(
            static::getConnection(),
            static::getGrammar()
        );

        return $builder->from($instance->getTable());
    }

    /**
     * Static method: Sadece trashed kayıtları getir
     *
     * @return QueryBuilder
     */
    public static function onlyTrashed(): QueryBuilder
    {
        return static::withTrashed()
            ->whereNotNull((new static())->getDeletedAtColumn());
    }

    /**
     * Deleted at değerini al
     *
     * @return DateTime|null
     * @throws Exception
     */
    public function getDeletedAt(): ?DateTime
    {
        $value = $this->getAttribute($this->getDeletedAtColumn());

        if ($value === null) {
            return null;
        }

        return $value instanceof DateTime ? $value : new DateTime($value);
    }

    /**
     * Timestamp oluştur
     *
     * @return string
     */
    protected function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }
}
