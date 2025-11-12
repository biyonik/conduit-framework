<?php

declare(strict_types=1);

namespace Conduit\Database\Traits;

/**
 * HasTimestamps Trait
 *
 * Model'e otomatik timestamp yönetimi ekler.
 * created_at: İlk kayıt anında set edilir
 * updated_at: Her update'te güncellenir
 *
 * @package Conduit\Database\Traits
 */
trait HasTimestamps
{
    /**
     * Timestamps aktif mi?
     *
     * Model'de $timestamps = false ile disable edilebilir.
     *
     * @return bool
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps ?? true;
    }

    /**
     * Created at kolon adı
     *
     * @return string
     */
    public function getCreatedAtColumn(): string
    {
        return $this->createdAtColumn ?? 'created_at';
    }

    /**
     * Updated at kolon adı
     *
     * @return string
     */
    public function getUpdatedAtColumn(): string
    {
        return $this->updatedAtColumn ?? 'updated_at';
    }

    /**
     * Timestamp'leri güncelle
     *
     * @return void
     */
    protected function updateTimestamps(): void
    {
        if (!$this->usesTimestamps()) {
            return;
        }

        $time = $this->freshTimestamp();

        // Eğer model yeni ise (insert), created_at set et
        if (!$this->exists && !$this->isDirty($this->getCreatedAtColumn())) {
            $this->setAttribute($this->getCreatedAtColumn(), $time);
        }

        // Her zaman updated_at'i güncelle
        if (!$this->isDirty($this->getUpdatedAtColumn())) {
            $this->setAttribute($this->getUpdatedAtColumn(), $time);
        }
    }

    /**
     * Yeni timestamp değeri oluştur
     *
     * @return string
     */
    protected function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Touch - sadece updated_at'i güncelle
     *
     * Kullanım: Model'de hiçbir değişiklik yapmadan
     * updated_at'i güncellemek için.
     *
     * @return bool
     */
    public function touch(): bool
    {
        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->setAttribute($this->getUpdatedAtColumn(), $this->freshTimestamp());

        return $this->save();
    }

    /**
     * Created at değerini al
     *
     * @return \DateTime|null
     */
    public function getCreatedAt(): ?\DateTime
    {
        $value = $this->getAttribute($this->getCreatedAtColumn());

        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTime ? $value : new \DateTime($value);
    }

    /**
     * Updated at değerini al
     *
     * @return \DateTime|null
     */
    public function getUpdatedAt(): ?\DateTime
    {
        $value = $this->getAttribute($this->getUpdatedAtColumn());

        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTime ? $value : new \DateTime($value);
    }

    /**
     * Created at değerini set et
     *
     * @param string|\DateTime $value
     * @return void
     */
    public function setCreatedAt(string|\DateTime $value): void
    {
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d H:i:s');
        }

        $this->setAttribute($this->getCreatedAtColumn(), $value);
    }

    /**
     * Updated at değerini set et
     *
     * @param string|\DateTime $value
     * @return void
     */
    public function setUpdatedAt(string|\DateTime $value): void
    {
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d H:i:s');
        }

        $this->setAttribute($this->getUpdatedAtColumn(), $value);
    }
}
