<?php

declare(strict_types=1);

namespace Conduit\Database\Contracts;

use Conduit\Database\Collection;
use Conduit\Database\Contracts\QueryBuilderInterface;

/**
 * Model Interface
 *
 * Active Record pattern için contract.
 * Eloquent ORM'den esinlenilmiştir.
 *
 * @package Conduit\Database\Contracts
 */
interface ModelInterface
{
    /**
     * Primary key'e göre model bul
     *
     * @param int $id Primary key değeri
     * @return static|null Model instance veya null
     */
    public static function find(int $id): ?static;

    /**
     * Primary key'e göre model bul veya exception fırlat
     *
     * @param int $id Primary key değeri
     * @return static Model instance
     * @throws \Conduit\Database\Exceptions\ModelNotFoundException
     */
    public static function findOrFail(int $id): static;

    /**
     * Tüm kayıtları getir
     *
     * @return Collection Model collection
     */
    public static function all(): Collection;

    /**
     * WHERE koşullu query başlat
     *
     * @param string $column Kolon adı
     * @param mixed $operator Operator veya value
     * @param mixed $value Value (operator verilmişse)
     * @return QueryBuilderInterface
     */
    public static function where(string $column, mixed $operator, mixed $value = null): QueryBuilderInterface;

    /**
     * Yeni model oluştur ve database'e kaydet
     *
     * Mass assignment koruması aktif (fillable property).
     *
     * @param array $attributes Model attribute'ları
     * @return static Oluşturulan model
     */
    public static function create(array $attributes): static;

    /**
     * Model'i database'e kaydet (INSERT veya UPDATE)
     *
     * @return bool Kayıt başarılı mı?
     */
    public function save(): bool;

    /**
     * Model'i update et
     *
     * @param array $attributes Update edilecek attribute'lar
     * @return bool Update başarılı mı?
     */
    public function update(array $attributes): bool;

    /**
     * Model'i database'den sil
     *
     * @return bool Silme başarılı mı?
     */
    public function delete(): bool;

    /**
     * Model'i array'e çevir
     *
     * Hidden attribute'lar hariç tutulur.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Model'i JSON string'e çevir
     *
     * @return string JSON representation
     */
    public function toJson(): string;

    /**
     * Attribute get
     *
     * @param string $key Attribute key
     * @return mixed
     */
    public function getAttribute(string $key): mixed;

    /**
     * Attribute set
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void;

    /**
     * Model üzerinde değişiklik var mı?
     *
     * @param string|null $key Belirli bir attribute kontrol et (null ise tümü)
     * @return bool
     */
    public function isDirty(?string $key = null): bool;

    /**
     * Model temiz mi? (hiç değişiklik yapılmadı mı?)
     *
     * @return bool
     */
    public function isClean(): bool;

    /**
     * Model database'den yeni mi yüklendi? (henüz kaydedilmedi mi?)
     *
     * @return bool
     */
    public function exists(): bool;

    /**
     * Primary key değerini al
     *
     * @return int|null
     */
    public function getKey(): ?int;

    /**
     * Tablo adını al
     *
     * @return string
     */
    public function getTable(): string;

    /**
     * Primary key kolon adını al
     *
     * @return string
     */
    public function getKeyName(): string;

    /**
     * Fillable attribute'ları al
     *
     * Mass assignment için izin verilen attribute'lar.
     *
     * @return array
     */
    public function getFillable(): array;

    /**
     * Hidden attribute'ları al
     *
     * toArray() ve toJson()'da gizlenecek attribute'lar.
     *
     * @return array
     */
    public function getHidden(): array;
}
