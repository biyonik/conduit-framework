<?php

declare(strict_types=1);

namespace Conduit\Database;

use Conduit\Database\Contracts\ConnectionInterface;
use Conduit\Database\Contracts\QueryBuilderInterface;
use Conduit\Database\Grammar\Grammar;
use Throwable;

/**
 * Query Builder
 *
 * Fluent API ile SQL query oluşturma.
 * Laravel Query Builder'dan esinlenilmiştir.
 *
 * Method chaining ile okunabilir query'ler:
 * $users = $db->table('users')
 *     ->where('active', 1)
 *     ->orderBy('created_at', 'DESC')
 *     ->limit(10)
 *     ->get();
 *
 * @package Conduit\Database
 */
class QueryBuilder implements QueryBuilderInterface
{
    /**
     * Query components (SELECT, FROM, WHERE, etc.)
     */
    protected array $components = [
        'select' => [],
        'from' => '',
        'joins' => [],
        'wheres' => [],
        'groups' => [],
        'havings' => [],
        'orders' => [],
        'limit' => null,
        'offset' => null,
    ];

    /**
     * Bound values (prepared statement için)
     */
    protected array $bindings = [];

    /**
     * Constructor
     *
     * @param ConnectionInterface $connection Database connection
     * @param Grammar $grammar SQL Grammar
     */
    public function __construct(
        protected ConnectionInterface $connection,
        protected Grammar $grammar
    ) {}

    /**
     * {@inheritdoc}
     */
    public function select(string|array ...$columns): self
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }

        $this->components['select'] = array_merge(
            $this->components['select'],
            $columns
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function from(string $table): self
    {
        $this->components['from'] = $table;
        return $this;
    }

    /**
     * Table set et (alias for from)
     *
     * @param string $table Tablo adı
     * @return self
     */
    public function table(string $table): self
    {
        return $this->from($table);
    }

    /**
     * {@inheritdoc}
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        // 2 parametre -> operator '=' olarak kabul et
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->components['wheres'][] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'and',
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->components['wheres'][] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or',
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereIn(string $column, array $values): self
    {
        $this->components['wheres'][] = [
            'type' => 'In',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and',
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->components['wheres'][] = [
            'type' => 'NotIn',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and',
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereNull(string $column): self
    {
        $this->components['wheres'][] = [
            'type' => 'Null',
            'column' => $column,
            'boolean' => 'and',
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereNotNull(string $column): self
    {
        $this->components['wheres'][] = [
            'type' => 'NotNull',
            'column' => $column,
            'boolean' => 'and',
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->components['wheres'][] = [
            'type' => 'Between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => 'and',
        ];

        $this->bindings[] = $min;
        $this->bindings[] = $max;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->components['joins'][] = [
            'type' => 'inner',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->components['joins'][] = [
            'type' => 'left',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->components['joins'][] = [
            'type' => 'right',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        $this->components['orders'][] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function groupBy(string|array ...$columns): self
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }

        $this->components['groups'] = array_merge(
            $this->components['groups'],
            $columns
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function having(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->components['havings'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'and',
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function limit(int $limit): self
    {
        $this->components['limit'] = $limit;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function offset(int $offset): self
    {
        $this->components['offset'] = $offset;
        return $this;
    }

    /**
     * DISTINCT keyword ekle
     *
     * @return self
     */
    public function distinct(): self
    {
        $this->components['distinct'] = true;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(): Collection
    {
        $sql = $this->toSql();
        $results = $this->connection->select($sql, $this->bindings);

        return new Collection($results);
    }

    /**
     * {@inheritdoc}
     */
    public function first(): ?array
    {
        $this->limit(1);
        return $this->get()->first();
    }

    /**
     * Find by primary key
     *
     * @param int $id Primary key değeri
     * @param string $column Primary key kolon adı
     * @return array|null
     */
    public function find(int $id, string $column = 'id'): ?array
    {
        return $this->where($column, $id)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function toSql(): string
    {
        return $this->grammar->compileSelect($this->components);
    }

    /**
     * {@inheritdoc}
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Query'yi yeniden başlat
     *
     * @return self
     */
    public function reset(): self
    {
        $this->components = [
            'select' => [],
            'from' => '',
            'joins' => [],
            'wheres' => [],
            'groups' => [],
            'havings' => [],
            'orders' => [],
            'limit' => null,
            'offset' => null,
        ];

        $this->bindings = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $column = '*'): int
    {
        $sql = $this->grammar->compileSelect(array_merge(
            $this->components,
            ['select' => ["COUNT({$column}) as aggregate"]]
        ));

        $result = $this->connection->select($sql, $this->bindings);

        return (int) ($result[0]['aggregate'] ?? 0);
    }

    /**
     * {@inheritdoc}
     */
    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * {@inheritdoc}
     */
    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * {@inheritdoc}
     */
    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    /**
     * {@inheritdoc}
     */
    public function sum(string $column): mixed
    {
        return $this->aggregate('SUM', $column);
    }

    /**
     * Aggregate function helper
     *
     * @param string $function Function name (COUNT, SUM, AVG, etc.)
     * @param string $column Kolon adı
     * @return mixed
     */
    protected function aggregate(string $function, string $column): mixed
    {
        $wrapped = $this->grammar->wrap($column);

        $sql = $this->grammar->compileSelect(array_merge(
            $this->components,
            ['select' => ["{$function}({$wrapped}) as aggregate"]]
        ));

        $result = $this->connection->select($sql, $this->bindings);

        return $result[0]['aggregate'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function insert(array $data): int
    {
        // Eğer multi-dimensional array ise bulk insert
        if (isset($data[0]) && is_array($data[0])) {
            return $this->bulkInsert($data);
        }

        $columns = array_keys($data);
        $values = array_values($data);

        $sql = $this->grammar->compileInsert($this->components['from'], $columns);

        return $this->connection->insert($sql, $values);
    }

    /**
     * Bulk insert (multiple rows)
     *
     * @param array $data Array of arrays
     * @return int Last insert ID (first row)
     */
    protected function bulkInsert(array $data): int
    {
        if (empty($data)) {
            return 0;
        }

        $columns = array_keys($data[0]);
        $table = $this->grammar->wrapTable($this->components['from']);
        $columnList = implode(', ', array_map(fn($col) => $this->grammar->wrap($col), $columns));

        // VALUES clause for each row
        $placeholders = [];
        $bindings = [];

        foreach ($data as $row) {
            $rowPlaceholders = implode(', ', array_fill(0, count($columns), '?'));
            $placeholders[] = "({$rowPlaceholders})";
            $bindings = array_merge($bindings, array_values($row));
        }

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES " . implode(', ', $placeholders);

        return $this->connection->insert($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $data): int
    {
        $columns = array_keys($data);
        $values = array_values($data);

        $sql = $this->grammar->compileUpdate($this->components['from'], $columns);

        // WHERE clause ekle
        if (!empty($this->components['wheres'])) {
            $sql .= ' ' . $this->grammar->compileWheres($this->components['wheres']);
            $values = array_merge($values, $this->bindings);
        }

        return $this->connection->update($sql, $values);
    }

    /**
     * Increment bir kolonu
     *
     * @param string $column Kolon adı
     * @param int $amount Artış miktarı
     * @return int Etkilenen satır sayısı
     */
    public function increment(string $column, int $amount = 1): int
    {
        $wrapped = $this->grammar->wrap($column);

        $table = $this->grammar->wrapTable($this->components['from']);
        $sql = "UPDATE {$table} SET {$wrapped} = {$wrapped} + ?";

        $bindings = [$amount];

        if (!empty($this->components['wheres'])) {
            $sql .= ' ' . $this->grammar->compileWheres($this->components['wheres']);
            $bindings = array_merge($bindings, $this->bindings);
        }

        return $this->connection->update($sql, $bindings);
    }

    /**
     * Decrement bir kolonu
     *
     * @param string $column Kolon adı
     * @param int $amount Azaltma miktarı
     * @return int Etkilenen satır sayısı
     */
    public function decrement(string $column, int $amount = 1): int
    {
        return $this->increment($column, -$amount);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(): int
    {
        $sql = $this->grammar->compileDelete($this->components['from']);

        // WHERE clause ekle
        if (!empty($this->components['wheres'])) {
            $sql .= ' ' . $this->grammar->compileWheres($this->components['wheres']);
        }

        return $this->connection->delete($sql, $this->bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function truncate(): bool
    {
        $sql = $this->grammar->compileTruncate($this->components['from']);
        return $this->connection->statement($sql);
    }

    /**
     * Pagination helper
     *
     * @param int $perPage Sayfa başına sonuç sayısı
     * @param int $page Sayfa numarası (1-indexed)
     * @return array Paginated results with meta
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page = max(1, $page); // Minimum 1

        // Total count
        $total = $this->count();

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Get data
        $data = $this->limit($perPage)->offset($offset)->get();

        return [
            'data' => $data->toArray(),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    /**
     * Kayıt var mı kontrol et
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Kayıt yok mu kontrol et
     *
     * @return bool
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Chunk ile sonuçları parça parça işle
     *
     * @param int $size Chunk size
     * @param callable $callback Callback function
     * @return void
     */
    public function chunk(int $size, callable $callback): void
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $size)->get();

            if ($results->isEmpty()) {
                break;
            }

            // Callback'i çağır
            if ($callback($results) === false) {
                break; // Stop if callback returns false
            }

            $page++;
        } while ($results->count() === $size);
    }

    /**
     * Page için query set et
     *
     * @param int $page Page number
     * @param int $perPage Per page
     * @return self
     */
    protected function forPage(int $page, int $perPage): self
    {
        $offset = ($page - 1) * $perPage;
        return $this->limit($perPage)->offset($offset);
    }

    /**
     * Raw SQL expression
     *
     * DIKKAT: SQL injection riski! Sadece güvenilir değerler için kullan.
     *
     * @param string $sql Raw SQL
     * @param array $bindings Bindings
     * @return array
     */
    public function raw(string $sql, array $bindings = []): array
    {
        return $this->connection->select($sql, $bindings);
    }

    /**
     * Transaction içinde işlem yap
     *
     * @param callable $callback Transaction içinde çalıştırılacak kod
     * @return mixed Callback'in return değeri
     * @throws Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $callback($this);
            $this->connection->commit();
            return $result;
        } catch (Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * Debug için SQL ve bindings yazdır
     *
     * @return array
     */
    public function dd(): array
    {
        return [
            'sql' => $this->toSql(),
            'bindings' => $this->getBindings(),
        ];
    }

    /**
     * Clone query builder
     *
     * @return self
     */
    public function clone(): self
    {
        return clone $this;
    }
}
