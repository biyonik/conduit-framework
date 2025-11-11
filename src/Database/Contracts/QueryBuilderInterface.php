<?php

declare(strict_types=1);

namespace Conduit\Database\Contracts;

use Conduit\Database\Collection;

/**
 * Query Builder Interface
 *
 * Fluent API ile SQL query oluşturma için contract.
 * Laravel Query Builder'dan esinlenilmiştir.
 * Method chaining desteği ile okunabilir query'ler.
 *
 * @package Conduit\Database\Contracts
 */
interface QueryBuilderInterface
{
    /**
     * SELECT clause - hangi kolonları seçeceğimizi belirt
     *
     * @param string|array ...$columns Seçilecek kolonlar
     * @return self Fluent interface için
     */
    public function select(string|array ...$columns): self;

    /**
     * FROM clause - hangi tablodan sorgu yapılacak
     *
     * @param string $table Tablo adı
     * @return self
     */
    public function from(string $table): self;

    /**
     * WHERE clause - basit eşitlik koşulu
     *
     * @param string $column Kolon adı
     * @param mixed $operator Operator veya value
     * @param mixed $value Value (operator verilmişse)
     * @return self
     */
    public function where(string $column, mixed $operator, mixed $value = null): self;

    /**
     * OR WHERE clause
     *
     * @param string $column Kolon adı
     * @param mixed $operator Operator veya value
     * @param mixed $value Value (operator verilmişse)
     * @return self
     */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self;

    /**
     * WHERE IN clause
     *
     * @param string $column Kolon adı
     * @param array $values Değerler array'i
     * @return self
     */
    public function whereIn(string $column, array $values): self;

    /**
     * WHERE NOT IN clause
     *
     * @param string $column Kolon adı
     * @param array $values Değerler array'i
     * @return self
     */
    public function whereNotIn(string $column, array $values): self;

    /**
     * WHERE NULL clause
     *
     * @param string $column Kolon adı
     * @return self
     */
    public function whereNull(string $column): self;

    /**
     * WHERE NOT NULL clause
     *
     * @param string $column Kolon adı
     * @return self
     */
    public function whereNotNull(string $column): self;

    /**
     * WHERE BETWEEN clause
     *
     * @param string $column Kolon adı
     * @param mixed $min Minimum değer
     * @param mixed $max Maximum değer
     * @return self
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self;

    /**
     * INNER JOIN clause
     *
     * @param string $table Join yapılacak tablo
     * @param string $first İlk kolon
     * @param string $operator Operator
     * @param string $second İkinci kolon
     * @return self
     */
    public function join(string $table, string $first, string $operator, string $second): self;

    /**
     * LEFT JOIN clause
     *
     * @param string $table Join yapılacak tablo
     * @param string $first İlk kolon
     * @param string $operator Operator
     * @param string $second İkinci kolon
     * @return self
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self;

    /**
     * RIGHT JOIN clause
     *
     * @param string $table Join yapılacak tablo
     * @param string $first İlk kolon
     * @param string $operator Operator
     * @param string $second İkinci kolon
     * @return self
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self;

    /**
     * ORDER BY clause
     *
     * @param string $column Sıralamanın yapılacağı kolon
     * @param string $direction Sıralama yönü (ASC veya DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self;

    /**
     * GROUP BY clause
     *
     * @param string|array ...$columns Gruplandırılacak kolonlar
     * @return self
     */
    public function groupBy(string|array ...$columns): self;

    /**
     * HAVING clause
     *
     * @param string $column Kolon adı
     * @param mixed $operator Operator veya value
     * @param mixed $value Value (operator verilmişse)
     * @return self
     */
    public function having(string $column, mixed $operator, mixed $value = null): self;

    /**
     * LIMIT clause
     *
     * @param int $limit Maksimum sonuç sayısı
     * @return self
     */
    public function limit(int $limit): self;

    /**
     * OFFSET clause
     *
     * @param int $offset Atlanacak sonuç sayısı
     * @return self
     */
    public function offset(int $offset): self;

    /**
     * Query'yi çalıştır ve tüm sonuçları dön
     *
     * @return Collection Sonuçlar koleksiyonu
     */
    public function get(): Collection;

    /**
     * İlk sonucu dön
     *
     * @return array|null İlk sonuç veya null
     */
    public function first(): ?array;

    /**
     * Sonuç sayısını dön (COUNT)
     *
     * @param string $column Sayılacak kolon (default: *)
     * @return int
     */
    public function count(string $column = '*'): int;

    /**
     * Maximum değeri dön (MAX)
     *
     * @param string $column Kolon adı
     * @return mixed
     */
    public function max(string $column): mixed;

    /**
     * Minimum değeri dön (MIN)
     *
     * @param string $column Kolon adı
     * @return mixed
     */
    public function min(string $column): mixed;

    /**
     * Ortalama değeri dön (AVG)
     *
     * @param string $column Kolon adı
     * @return float
     */
    public function avg(string $column): float;

    /**
     * Toplam değeri dön (SUM)
     *
     * @param string $column Kolon adı
     * @return mixed
     */
    public function sum(string $column): mixed;

    /**
     * INSERT query çalıştır
     *
     * @param array $data Insert edilecek data (associative array)
     * @return int Last insert ID
     */
    public function insert(array $data): int;

    /**
     * UPDATE query çalıştır
     *
     * @param array $data Update edilecek data
     * @return int Etkilenen satır sayısı
     */
    public function update(array $data): int;

    /**
     * DELETE query çalıştır
     *
     * @return int Silinen satır sayısı
     */
    public function delete(): int;

    /**
     * TRUNCATE table
     *
     * Tabloyu tamamen temizle (hızlı delete, auto-increment sıfırla)
     *
     * @return bool
     */
    public function truncate(): bool;

    /**
     * SQL query'yi string olarak al (debugging için)
     *
     * @return string
     */
    public function toSql(): string;

    /**
     * Bind edilmiş değerleri al (debugging için)
     *
     * @return array
     */
    public function getBindings(): array;
}
