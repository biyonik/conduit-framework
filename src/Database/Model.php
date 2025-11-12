<?php

declare(strict_types=1);

namespace Conduit\Database;

use Conduit\Database\Contracts\ConnectionInterface;
use Conduit\Database\Contracts\ModelInterface;
use Conduit\Database\Exceptions\ModelNotFoundException;
use DateTime;
use Exception;
use JsonException;
use ReflectionClass;
use Conduit\Database\Relations\HasOne;
use Conduit\Database\Relations\HasMany;
use Conduit\Database\Relations\BelongsTo;
use Conduit\Database\Relations\BelongsToMany;

/**
 * Model Base Class
 *
 * Active Record pattern implementation.
 * Laravel Eloquent'ten esinlenilmiştir.
 *
 * Her model bir database tablosunu temsil eder.
 * CRUD operasyonları, relationships, events destekler.
 *
 * @package Conduit\Database
 */
abstract class Model implements ModelInterface
{
    /**
     * Database connection instance
     */
    protected static ?ConnectionInterface $connection = null;

    /**
     * Tablo adı (override edilebilir)
     *
     * Default: class name'in snake_case plural hali
     * Örn: User -> users, Post -> posts
     */
    protected string $table = '';

    /**
     * Primary key kolon adı
     */
    protected string $primaryKey = 'id';

    /**
     * Primary key type (int, string, uuid)
     */
    protected string $keyType = 'int';

    /**
     * Auto-incrementing primary key mi?
     */
    protected bool $incrementing = true;

    /**
     * Timestamps kullanılıyor mu? (created_at, updated_at)
     */
    protected bool $timestamps = true;

    /**
     * Soft delete kullanılıyor mu? (deleted_at)
     */
    protected bool $softDeletes = false;

    /**
     * Mass assignment için izin verilen attribute'lar
     *
     * Güvenlik: Sadece bu attribute'lar create() ile set edilebilir
     */
    protected array $fillable = [];

    /**
     * Mass assignment için yasak attribute'lar
     *
     * $fillable boşsa, buradakiler HARİÇ hepsi izinli
     */
    protected array $guarded = ['*'];

    /**
     * JSON/Array'e çevrilirken gizlenecek attribute'lar
     *
     * Örn: password, remember_token
     */
    protected array $hidden = [];

    /**
     * JSON/Array'e çevrilirken her zaman gösterilecek attribute'lar
     */
    protected array $visible = [];

    /**
     * Attribute casting (type conversion)
     *
     * Örn: ['is_admin' => 'boolean', 'price' => 'float']
     */
    protected array $casts = [];

    /**
     * Model attribute'ları (database kolonları)
     */
    protected array $attributes = [];

    /**
     * Orijinal attribute'lar (değişiklik takibi için)
     */
    protected array $original = [];

    /**
     * Model database'de mevcut mu? (saved vs new)
     */
    protected bool $exists = false;

    /**
     * Loaded relationships
     *
     * @var array
     */
    protected array $relations = [];

    /**
     * Constructor
     *
     * @param array $attributes Initial attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Tablo adını al
     *
     * @return string
     */
    public function getTable(): string
    {
        if (empty($this->table)) {
            // Auto-generate from class name
            $className = (new ReflectionClass($this))->getShortName();
            $this->table = strtolower($className) . 's'; // Simple pluralization
        }

        return $this->table;
    }

    /**
     * Primary key kolon adını al
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Primary key değerini al
     *
     * @return int|string|null
     * @throws JsonException
     */
    public function getKey(): int|string|null
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Primary key değerini set et
     *
     * @param int|string $value
     * @return void
     */
    public function setKey(int|string $value): void
    {
        $this->setAttribute($this->getKeyName(), $value);
    }

    /**
     * Relationship sonucunu model'e attach et
     *
     * Eager loading tarafından kullanılır
     *
     * @param string $relation Relationship adı
     * @param mixed $value Relationship sonucu (Model|Collection|null)
     * @return self
     */
    public function setRelation(string $relation, mixed $value): self
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    /**
     * Relationship yüklenmiş mi kontrol et
     *
     * @param string $relation Relationship name
     * @return bool
     */
    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    /**
     * Relationship'i al (eğer yüklenmişse)
     *
     * @param string $relation Relationship adı
     * @return mixed
     */
    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * Query builder instance al
     *
     * @return QueryBuilder
     */
    public static function query(): QueryBuilder
    {
        return (new static())->newQuery();
    }

    /**
     * Yeni query builder oluştur
     *
     * @return QueryBuilder
     */
    final protected function newQuery(): QueryBuilder
    {
        return (new QueryBuilder(self::getConnection(), self::getGrammar()))
            ->from($this->getTable());
    }

    /**
     * Database connection'ı al
     *
     * @return ConnectionInterface
     */
    protected static function getConnection(): ConnectionInterface
    {
        if (static::$connection === null) {
            // Framework'ten connection al (dependency injection)
            static::$connection = app()->make(ConnectionInterface::class);
        }

        return static::$connection;
    }

    /**
     * SQL Grammar'ı al
     *
     * @return Grammar\Grammar
     */
    protected static function getGrammar(): Grammar\Grammar
    {
        $driver = static::getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => new Grammar\SQLiteGrammar(),
            'pgsql' => new Grammar\PostgreSQLGrammar(),
            default => new Grammar\MySQLGrammar(),
        };
    }

    /**
     * {@inheritdoc}
     */
    public static function find(int|string $id): ?static
    {
        $result = static::query()
            ->where((new static())->getKeyName(), $id)
            ->first();

        if ($result === null) {
            return null;
        }

        return (new static())->newFromArray($result);
    }

    /**
     * {@inheritdoc}
     */
    public static function findOrFail(int|string $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new ModelNotFoundException(static::class, $id);
        }

        return $model;
    }

    /**
     * {@inheritdoc}
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * {@inheritdoc}
     */
    public static function where(string $column, mixed $operator, mixed $value = null): QueryBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function save(): bool
    {
        // Eğer model zaten database'de varsa UPDATE, yoksa INSERT
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * INSERT işlemi yap
     *
     * @return bool
     */
    protected function performInsert(): bool
    {
        // Timestamps set et (created_at, updated_at)
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        // Fire creating event
        $this->fireModelEvent('creating');

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

        return true;
    }

    /**
     * UPDATE işlemi yap
     *
     * @return bool
     * @throws JsonException
     */
    protected function performUpdate(): bool
    {
        // Dirty check: Hiç değişiklik yoksa UPDATE'e gerek yok
        if (!$this->isDirty()) {
            return true;
        }

        // Timestamps güncelle (updated_at)
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        // Fire updating event
        $this->fireModelEvent('updating');

        // Sadece değişen attribute'ları al
        $dirty = $this->getDirty();

        // UPDATE query
        $affected = $this->newQuery()
            ->where($this->getKeyName(), $this->getKey())
            ->update($dirty);

        $this->syncOriginal();

        // Fire updated event
        $this->fireModelEvent('updated');

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // Fire deleting event
        $this->fireModelEvent('deleting');

        // Soft delete mi?
        if ($this->softDeletes) {
            return $this->performSoftDelete();
        }

        // Hard delete
        $deleted = $this->newQuery()
            ->where($this->getKeyName(), $this->getKey())
            ->delete();

        $this->exists = false;

        // Fire deleted event
        $this->fireModelEvent('deleted');

        return $deleted > 0;
    }

    /**
     * Soft delete yap
     *
     * @return bool
     * @throws JsonException
     */
    protected function performSoftDelete(): bool
    {
        $this->setAttribute('deleted_at', date('Y-m-d H:i:s'));
        return $this->save();
    }

    /**
     * Attribute'ları fill et (mass assignment)
     *
     * @param array $attributes
     * @return self
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Attribute fillable mı kontrol et
     *
     * @param string $key
     * @return bool
     */
    protected function isFillable(string $key): bool
    {
        // Eğer fillable belirtilmişse, sadece onlar izinli
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable, true);
        }

        // Eğer guarded belirtilmişse, onlar hariç hepsi izinli
        if (in_array('*', $this->guarded, true)) {
            return false;
        }

        return !in_array($key, $this->guarded, true);
    }

    /**
     * Fillable attribute'ları al
     *
     * @return array
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Hidden attribute'ları al
     *
     * @return array
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Model event fire et
     *
     * Override edilebilir (FiresEvents trait tarafından)
     *
     * @param string $event Event name
     * @return void
     */
    protected function fireModelEvent(string $event): void
    {
        // Base implementation: Do nothing
        // FiresEvents trait bunu override eder
    }

    /**
     * Timestamps güncelle
     *
     * @return void
     */
    protected function updateTimestamps(): void
    {
        $time = date('Y-m-d H:i:s');

        if (!$this->exists) {
            $this->setAttribute('created_at', $time);
        }

        $this->setAttribute('updated_at', $time);
    }

    /**
     * Original attributes'ı sync et (değişiklik takibi için)
     *
     * @return void
     */
    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function getAttribute(string $key): mixed
    {
        // Eğer attribute mevcutsa
        if (array_key_exists($key, $this->attributes)) {
            return $this->castAttribute($key, $this->attributes[$key]);
        }

        // Accessor method var mı kontrol et (get{Key}Attribute)
        $accessor = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->$accessor();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute(string $key, mixed $value): void
    {
        // Mutator method var mı kontrol et (set{Key}Attribute)
        $mutator = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        if (method_exists($this, $mutator)) {
            $this->$mutator($value);
            return;
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Attribute'ı cast et (type conversion)
     *
     * @param string $key Attribute key
     * @param mixed $value Original value
     * @return mixed Casted value
     * @throws JsonException
     * @throws Exception
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if (!isset($this->casts[$key])) {
            return $value;
        }

        $castType = $this->casts[$key];

        return match ($castType) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : (array) $value,
            'json' => is_string($value) ? json_decode($value, false, 512, JSON_THROW_ON_ERROR) : $value,
            'datetime' => $this->asDateTime($value),
            'date' => $this->asDate($value),
            default => $value,
        };
    }

    /**
     * DateTime casting helper
     *
     * @param mixed $value
     * @return DateTime|null
     * @throws Exception
     */
    protected function asDateTime(mixed $value): ?DateTime
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTime) {
            return $value;
        }

        return new DateTime($value);
    }

    /**
     * Date casting helper
     *
     * @param mixed $value
     * @return string|null
     */
    protected function asDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }

        return date('Y-m-d', strtotime($value));
    }

    /**
     * {@inheritdoc}
     */
    public function isDirty(?string $key = null): bool
    {
        // Belirli bir key kontrol ediliyor mu?
        if ($key !== null) {
            if (!array_key_exists($key, $this->attributes)) {
                return false;
            }

            return $this->attributes[$key] !== ($this->original[$key] ?? null);
        }

        // Tüm attribute'lar kontrol et
        foreach ($this->attributes as $k => $value) {
            if ($value !== ($this->original[$k] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Değişen attribute'ları al (dirty attributes)
     *
     * @return array
     */
    public function getDirty(): array
    {

        return array_filter($this->attributes, function ($value, $key) {
            return !array_key_exists($key, $this->original) || $value !== $this->original[$key];
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * {@inheritdoc}
     */
    public function isClean(): bool
    {
        return !$this->isDirty();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;

        // Accessor'ları ekle
        foreach ($this->getArrayableAccessors() as $key) {
            $attributes[$key] = $this->getAttribute($key);
        }

        // Hidden attribute'ları çıkar
        foreach ($this->hidden as $key) {
            unset($attributes[$key]);
        }

        // Visible belirtilmişse, sadece onları göster
        if (!empty($this->visible)) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        return $attributes;
    }

    /**
     * Array'e çevrilebilir accessor'ları al
     *
     * @return array
     */
    protected function getArrayableAccessors(): array
    {
        $accessors = [];

        foreach (get_class_methods($this) as $method) {
            if (preg_match('/^get(.+)Attribute$/', $method, $matches)) {
                $key = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $matches[1]));

                // Hidden değilse ekle
                if (!in_array($key, $this->hidden, true)) {
                    $accessors[] = $key;
                }
            }
        }

        return $accessors;
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Fresh model instance al (database'den yeniden yükle)
     *
     * @return static|null
     * @throws JsonException
     */
    public function fresh(): ?static
    {
        if (!$this->exists) {
            return null;
        }

        return static::find($this->getKey());
    }

    /**
     * Model'i refresh et (database'den yeniden yükle)
     *
     * @return self
     * @throws JsonException
     */
    public function refresh(): self
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = $this->fresh();

        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Clone model
     *
     * @return static
     */
    public function replicate(): static
    {
        $attributes = $this->attributes;

        // Primary key ve timestamps'ı çıkar
        unset(
            $attributes[$this->getKeyName()],
            $attributes['created_at'],
            $attributes['updated_at']
        );

        $replica = new static($attributes);
        $replica->exists = false;

        return $replica;
    }

    /**
     * HasOne relationship tanımla
     *
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key (default: parent_id)
     * @param string|null $localKey Local key (default: id)
     * @return HasOne
     */
    protected function hasOne(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ): HasOne {
        return new HasOne($this, $related, $foreignKey, $localKey);
    }

    /**
     * HasMany relationship tanımla
     *
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key (default: parent_id)
     * @param string|null $localKey Local key (default: id)
     * @return HasMany
     */
    protected function hasMany(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ): HasMany {
        return new HasMany($this, $related, $foreignKey, $localKey);
    }

    /**
     * BelongsTo relationship tanımla
     *
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key (default: related_id)
     * @param string|null $ownerKey Owner key (default: id)
     * @return BelongsTo
     */
    protected function belongsTo(
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null
    ): BelongsTo {
        return new BelongsTo($this, $related, $foreignKey, $ownerKey);
    }

    /**
     * BelongsToMany relationship tanımla
     *
     * @param string $related Related model class
     * @param string|null $pivotTable Pivot table name
     * @param string|null $foreignPivotKey Foreign key on pivot
     * @param string|null $relatedPivotKey Related key on pivot
     * @param string|null $parentKey Parent model key
     * @param string|null $relatedKey Related model key
     * @return BelongsToMany
     */
    protected function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): BelongsToMany {
        return new BelongsToMany(
            $this,
            $related,
            $pivotTable,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }

    /**
     * Relationship load et (lazy loading)
     *
     * @param string $key
     * @return mixed
     * @throws JsonException
     */
    public function __get(string $key): mixed
    {
        // Eğer attribute varsa onu dön
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        // Eğer relation zaten yüklenmişse, onu dön
        if ($this->relationLoaded($key)) {
            return $this->getRelation($key);
        }

        // Relationship mi kontrol et
        if (method_exists($this, $key)) {
            $relation = $this->$key();

            // Eğer Relation instance ise, sonuçları getir ve cache'le
            if ($relation instanceof Relations\Relation) {
                $result = $relation->getResults();
                $this->setRelation($key, $result);
                return $result;
            }
        }

        return null;
    }

    /**
     * Eager loading - relationship'leri önceden yükle
     *
     * N+1 problem çözümü.
     *
     * Usage:
     * $users = User::with('posts', 'comments')->get();
     *
     * @param string|array ...$relations Relationship names
     * @return QueryBuilder
     */
    public static function with(string|array ...$relations): QueryBuilder
    {
        // İlk parametre array ise, onu kullan
        if (count($relations) === 1 && is_array($relations[0])) {
            $relations = $relations[0];
        }

        $instance = new static();
        // QueryBuilder'a eager load relationship'leri ekle
        return $instance->newQuery()->with($relations);
    }

    /**
     * QueryBuilder'dan gelen sonuçları Model Collection'a çevir
     *
     * Bu method QueryBuilder::get() tarafından çağrılacak
     *
     * @param array $results Raw database results
     * @param array $eagerLoad Eager load edilecek relationship'ler
     * @return Collection Model collection
     */
    public static function hydrate(array $results, array $eagerLoad = []): Collection
    {
        $instance = new static();

        // Her result'ı Model instance'a çevir
        $models = array_map(function ($result) use ($instance) {
            return $instance->newFromArray($result);
        }, $results);

        $collection = new Collection($models);

        // Eğer eager load varsa, relationship'leri yükle
        if (!empty($eagerLoad)) {
            $collection = static::eagerLoadRelations($collection, $eagerLoad);
        }

        return $collection;
    }

    /**
     * Relationship'leri eager load et
     *
     * @param Collection $models Model collection
     * @param array $relations Yüklenecek relationship'ler
     * @return Collection Updated collection
     */
    protected static function eagerLoadRelations(Collection $models, array $relations): Collection
    {
        foreach ($relations as $name) {
            // Her relationship için constraint'siz yeni relation instance oluştur
            $models = static::eagerLoadRelation($models, $name);
        }

        return $models;
    }

    /**
     * Tek bir relationship'i eager load et
     *
     * @param Collection $models Model collection
     * @param string $name Relationship adı
     * @return Collection Updated collection
     */
    protected static function eagerLoadRelation(Collection $models, string $name): Collection
    {
        if ($models->isEmpty()) {
            return $models;
        }

        // İlk model'den relation instance al
        $relation = $models->first()->$name();

        // Eğer relation method yoksa, skip
        if (!$relation instanceof Relations\Relation) {
            return $models;
        }

        // Constraint'siz query'yi al ve tüm related model'leri eager load et
        $relation->addEagerConstraints($models);

        // Query'yi çalıştır ve sonuçları al
        $results = $relation->getEager();

        // Sonuçları model'lere match et
        return $relation->match($models, $results, $name);
    }


    /**
     * Array'den model oluştur (internal use)
     *
     * @param array $attributes
     * @return static
     */
    public function newFromArray(array $attributes): static
    {
        $model = new static();
        $model->attributes = $attributes;
        $model->original = $attributes;
        $model->exists = true;

        return $model;
    }

// ==================== Magic Methods ====================

    /**
     * Magic setter
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Magic unset
     *
     * @param string $key
     * @return void
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * String representation
     *
     * @return string
     * @throws JsonException
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Static call forwarding to query builder
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static())->$method(...$parameters);
    }

    /**
     * Instance call forwarding to query builder
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * Array representation (for debugging)
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'attributes' => $this->attributes,
            'original' => $this->original,
            'exists' => $this->exists,
            'table' => $this->getTable(),
        ];
    }
}
