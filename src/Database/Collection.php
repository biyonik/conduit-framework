<?php

declare(strict_types=1);

namespace Conduit\Database;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * Collection
 *
 * Array wrapper with utility methods.
 * Laravel Collection'dan esinlenilmiştir.
 *
 * Query sonuçlarını wrap eder ve functional programming
 * metodları (map, filter, reduce, etc.) sağlar.
 *
 * @package Conduit\Database
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * Constructor
     *
     * @param array $items Collection items
     */
    public function __construct(
        protected array $items = []
    ) {}

    /**
     * Tüm item'ları dön
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * İlk item'ı dön
     *
     * @param callable|null $callback Filter callback (optional)
     * @return mixed
     */
    public function first(?callable $callback = null): mixed
    {
        if ($callback === null) {
            return $this->items[0] ?? null;
        }

        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Son item'ı dön
     *
     * @param callable|null $callback Filter callback (optional)
     * @return mixed
     */
    public function last(?callable $callback = null): mixed
    {
        if ($callback === null) {
            return !empty($this->items) ? end($this->items) : null;
        }

        $filtered = array_reverse($this->items);
        foreach ($filtered as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Her item için callback çalıştır ve yeni collection dön
     *
     * @param callable $callback Map function
     * @return static
     */
    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items, array_keys($this->items)));
    }

    /**
     * Filter callback'e göre item'ları filtrele
     *
     * @param callable $callback Filter function
     * @return static
     */
    public function filter(callable $callback): static
    {
        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Belirli bir kolonu pluck et (değerleri çıkar)
     *
     * @param string $column Çıkarılacak kolon
     * @param string|null $key Key olarak kullanılacak kolon (optional)
     * @return static
     */
    public function pluck(string $column, ?string $key = null): static
    {
        $result = [];

        foreach ($this->items as $item) {
            $value = $this->getItemValue($item, $column);

            if ($key !== null) {
                $keyValue = $this->getItemValue($item, $key);
                $result[$keyValue] = $value;
            } else {
                $result[] = $value;
            }
        }

        return new static($result);
    }

    /**
     * Item'dan value al (array veya object olabilir)
     *
     * @param mixed $item Item
     * @param string $key Key
     * @return mixed
     */
    protected function getItemValue(mixed $item, string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        if (is_object($item)) {
            // getAttribute() metodu varsa kullan (Model için)
            if (method_exists($item, 'getAttribute')) {
                return $item->getAttribute($key);
            }

            // Property access
            return $item->$key ?? null;
        }

        return null;
    }

    /**
     * Item'ları belirli bir key'e göre grupla
     *
     * @param string $key Gruplandırma key'i
     * @return static
     */
    public function groupBy(string $key): static
    {
        $result = [];

        foreach ($this->items as $item) {
            $groupKey = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            $result[$groupKey][] = $item;
        }

        return new static($result);
    }

    /**
     * Item'ları belirli bir key'e göre key-value pair'e çevir
     *
     * @param string $key Key kolon
     * @param string $value Value kolon
     * @return array
     */
    public function keyBy(string $key, string $value): array
    {
        $result = [];

        foreach ($this->items as $item) {
            $itemKey = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            $itemValue = is_array($item) ? ($item[$value] ?? null) : ($item->$value ?? null);
            $result[$itemKey] = $itemValue;
        }

        return $result;
    }

    /**
     * Item'ları sırala
     *
     * @param string|callable $key Sort key or callback
     * @param string $direction Sort direction (ASC or DESC)
     * @return static
     */
    public function sortBy(string|callable $key, string $direction = 'ASC'): static
    {
        $items = $this->items;

        if (is_callable($key)) {
            uasort($items, $key);
        } else {
            uasort($items, static function ($a, $b) use ($key, $direction) {
                $aValue = is_array($a) ? ($a[$key] ?? null) : ($a->$key ?? null);
                $bValue = is_array($b) ? ($b[$key] ?? null) : ($b->$key ?? null);

                if ($direction === 'DESC') {
                    return $bValue <=> $aValue;
                }

                return $aValue <=> $bValue;
            });
        }

        return new static($items);
    }

    /**
     * Item'ları ters çevir
     *
     * @return static
     */
    public function reverse(): static
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Unique item'ları dön
     *
     * @param string|null $key Unique key (optional)
     * @return static
     */
    public function unique(?string $key = null): static
    {
        if ($key === null) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $exists = [];
        $result = [];

        foreach ($this->items as $item) {
            $value = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);

            if (!in_array($value, $exists, true)) {
                $exists[] = $value;
                $result[] = $item;
            }
        }

        return new static($result);
    }

    /**
     * Collection'ı chunk'lara böl
     *
     * @param int $size Chunk size
     * @return static Collection of collections
     */
    public function chunk(int $size): static
    {
        $chunks = [];

        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * İlk N item'ı al
     *
     * @param int $limit Limit
     * @return static
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return new static(array_slice($this->items, $limit));
        }

        return new static(array_slice($this->items, 0, $limit));
    }

    /**
     * İlk N item'ı atla
     *
     * @param int $offset Offset
     * @return static
     */
    public function skip(int $offset): static
    {
        return new static(array_slice($this->items, $offset));
    }

    /**
     * Collection boş mu?
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Collection'da item var mı?
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Belirli bir value collection'da var mı?
     *
     * @param mixed $value Aranacak değer
     * @param string|null $key Key (optional)
     * @return bool
     */
    public function contains(mixed $value, ?string $key = null): bool
    {
        if ($key === null) {
            return in_array($value, $this->items, true);
        }

        foreach ($this->items as $item) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            if ($itemValue === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reduce collection to single value
     *
     * @param callable $callback Reduce function
     * @param mixed $initial Initial value
     * @return mixed
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Collection'ı array'e çevir
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_map(static function ($value) {
            return is_object($value) && method_exists($value, 'toArray')
                ? $value->toArray()
                : $value;
        }, $this->items);
    }

    /**
     * Collection'ı JSON'a çevir
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Her item için callback çalıştır
     *
     * @param callable $callback Callback function
     * @return self
     */
    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * İki collection'ı birleştir
     *
     * @param array|static $items Eklenecek item'lar
     * @return static
     */
    public function merge(Collection|array $items): static
    {
        if ($items instanceof self) {
            $items = $items->all();
        }

        return new static(array_merge($this->items, $items));
    }

    // ==================== Magic Methods & Interfaces ====================

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Magic getter
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->items[$key] ?? null;
    }

    /**
     * Magic setter
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    /**
     * Magic isset
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->items[$key]);
    }

    /**
     * Magic unset
     *
     * @param string $key
     * @return void
     */
    public function __unset(string $key): void
    {
        unset($this->items[$key]);
    }

    /**
     * String representation
     *
     * @return string
     * @throws \JsonException
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
