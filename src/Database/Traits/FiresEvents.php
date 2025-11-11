<?php

declare(strict_types=1);

namespace Conduit\Database\Traits;

/**
 * FiresEvents Trait
 *
 * Model'e lifecycle event desteği ekler.
 *
 * Available Events:
 * - creating: Model INSERT edilmeden önce
 * - created: Model INSERT edildikten sonra
 * - updating: Model UPDATE edilmeden önce
 * - updated: Model UPDATE edildikten sonra
 * - saving: Model INSERT veya UPDATE edilmeden önce
 * - saved: Model INSERT veya UPDATE edildikten sonra
 * - deleting: Model DELETE edilmeden önce
 * - deleted: Model DELETE edildikten sonra
 * - restoring: Soft deleted model restore edilmeden önce
 * - restored: Soft deleted model restore edildikten sonra
 *
 * @package Conduit\Database\Traits
 */
trait FiresEvents
{
    /**
     * Event listener'ları (static)
     *
     * Her model class'ı için ayrı listener array'i
     */
    protected static array $eventListeners = [];

    /**
     * Boot method - Model lifecycle için static constructor
     *
     * Model her instantiate edildiğinde çağrılır.
     * Alt sınıflar bu metodu override edebilir.
     *
     * @return void
     */
    protected static function boot(): void
    {
        // Alt sınıflar burada kendi event'lerini register edebilir
    }

    /**
     * Event listener kaydet
     *
     * @param string $event Event name (creating, created, etc.)
     * @param callable $callback Callback function
     * @return void
     */
    public static function registerEvent(string $event, callable $callback): void
    {
        $class = static::class;

        if (!isset(static::$eventListeners[$class])) {
            static::$eventListeners[$class] = [];
        }

        if (!isset(static::$eventListeners[$class][$event])) {
            static::$eventListeners[$class][$event] = [];
        }

        static::$eventListeners[$class][$event][] = $callback;
    }

    /**
     * Creating event listener kaydet
     *
     * @param callable $callback
     * @return void
     */
    public static function creating(callable $callback): void
    {
        static::registerEvent('creating', $callback);
    }

    /**
     * Created event listener kaydet
     *
     * @param callable $callback
     * @return void
     */
    public static function created(callable $callback): void
    {
        static::registerEvent('created', $callback);
    }

    /**
     * Updating event listener kaydet
     *
     * @param callable $callback
     * @return void
     */
    public static function updating(callable $callback): void
    {
        static::registerEvent('updating', $callback);
    }

    /**
     * Updated event listener kaydet
     *
     * @param callable $callback
     * @return void
     */
    public static function updated(callable $callback): void
    {
        static::registerEvent('updated', $callback);
    }

    /**
     * Saving event listener kaydet (creating veya updating'den önce)
     *
     * @param callable $callback
     * @return void
     */
    public static function saving(callable $callback): void
    {
        static::registerEvent('saving', $callback);
    }

    /**
     * Saved event listener kaydet (created veya updated'den sonra)
     *
     * @param callable $callback
     * @return void
     */
    public static function saved(callable $callback): void
    {
        static::registerEvent('saved', $callback);
    }

    /**
     * Deleting event listener kaydet
     *
     * @param callable $callback
     * @return void
     */
    public static function deleting(callable $callback): void
    {
        static::registerEvent('deleting', $callback);
    }

    /**
     * Deleted event listener kaydet
     *
     * @param callable $callback
     * @return void
     */
    public static function deleted(callable $callback): void
    {
        static::registerEvent('deleted', $callback);
    }

    /**
     * Restoring event listener kaydet (soft delete restore)
     *
     * @param callable $callback
     * @return void
     */
    public static function restoring(callable $callback): void
    {
        static::registerEvent('restoring', $callback);
    }

    /**
     * Restored event listener kaydet (soft delete restore)
     *
     * @param callable $callback
     * @return void
     */
    public static function restored(callable $callback): void
    {
        static::registerEvent('restored', $callback);
    }

    /**
     * Model event fire et
     *
     * @param string $event Event name
     * @return bool False dönerse operation iptal edilir
     */
    protected function fireModelEvent(string $event): bool
    {
        $class = static::class;

        // Event listener'lar yoksa devam et
        if (!isset(static::$eventListeners[$class][$event])) {
            return true;
        }

        // Her listener'ı çağır
        foreach (static::$eventListeners[$class][$event] as $callback) {
            $result = $callback($this);

            // Eğer listener false dönerse, operation'ı iptal et
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * INSERT işlemi yap (override with events)
     *
     * @return bool
     */
    protected function performInsert(): bool
    {
        // Fire saving event
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        // Fire creating event
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // Timestamps set et (created_at, updated_at)
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        // INSERT query
        $id = $this->newQuery()->insert($this->attributes);

        // Auto-incrementing ise primary key set et
        if ($this->incrementing) {
            $this->setKey($id);
        }

        $this->exists = true;
        $this->syncOriginal();

        // Fire created event
        $this->fireModelEvent('created');

        // Fire saved event
        $this->fireModelEvent('saved');

        return true;
    }

    /**
     * UPDATE işlemi yap (override with events)
     *
     * @return bool
     */
    protected function performUpdate(): bool
    {
        // Dirty check: Hiç değişiklik yoksa UPDATE'e gerek yok
        if (!$this->isDirty()) {
            return true;
        }

        // Fire saving event
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        // Fire updating event
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // Timestamps güncelle (updated_at)
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        // Sadece değişen attribute'ları al
        $dirty = $this->getDirty();

        // UPDATE query
        $affected = $this->newQuery()
            ->where($this->getKeyName(), $this->getKey())
            ->update($dirty);

        $this->syncOriginal();

        // Fire updated event
        $this->fireModelEvent('updated');

        // Fire saved event
        $this->fireModelEvent('saved');

        return $affected > 0;
    }

    /**
     * Tüm event listener'ları temizle (testing için)
     *
     * @return void
     */
    public static function clearEventListeners(): void
    {
        static::$eventListeners[static::class] = [];
    }

    /**
     * Belirli bir event'in listener'larını al
     *
     * @param string $event Event name
     * @return array
     */
    public static function getEventListeners(string $event): array
    {
        $class = static::class;

        return static::$eventListeners[$class][$event] ?? [];
    }

    /**
     * Tüm event listener'ları al
     *
     * @return array
     */
    public static function getAllEventListeners(): array
    {
        return static::$eventListeners[static::class] ?? [];
    }
}
