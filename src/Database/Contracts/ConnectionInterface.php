<?php

declare(strict_types=1);

namespace Conduit\Database\Contracts;

use PDO;

/**
 * Database Connection Interface
 *
 * Veritabanı bağlantısı için contract.
 * PDO wrapper görevi görür, reconnection ve transaction desteği sağlar.
 *
 * @package Conduit\Database\Contracts
 */
interface ConnectionInterface
{
    /**
     * PDO bağlantısını al
     *
     * Lazy connection: İlk erişimde bağlantı açılır.
     *
     * @return PDO
     */
    public function getPdo(): PDO;

    /**
     * Yeni bir database query çalıştır
     *
     * Prepared statement kullanır, SQL injection'dan korunur.
     *
     * @param string $query SQL sorgusu (placeholder'larla)
     * @param array $bindings Bind edilecek değerler
     * @return bool Query başarılı mı?
     */
    public function statement(string $query, array $bindings = []): bool;

    /**
     * SELECT query çalıştır ve sonuçları dön
     *
     * @param string $query SQL SELECT sorgusu
     * @param array $bindings Bind edilecek değerler
     * @return array Sorgu sonuçları (associative array)
     */
    public function select(string $query, array $bindings = []): array;

    /**
     * INSERT query çalıştır
     *
     * @param string $query SQL INSERT sorgusu
     * @param array $bindings Bind edilecek değerler
     * @return int Last insert ID
     */
    public function insert(string $query, array $bindings = []): int;

    /**
     * UPDATE query çalıştır
     *
     * @param string $query SQL UPDATE sorgusu
     * @param array $bindings Bind edilecek değerler
     * @return int Etkilenen satır sayısı
     */
    public function update(string $query, array $bindings = []): int;

    /**
     * DELETE query çalıştır
     *
     * @param string $query SQL DELETE sorgusu
     * @param array $bindings Bind edilecek değerler
     * @return int Silinen satır sayısı
     */
    public function delete(string $query, array $bindings = []): int;

    /**
     * Transaction başlat
     *
     * ACID garantisi için transaction kullan.
     *
     * @return bool Transaction başarıyla başlatıldı mı?
     */
    public function beginTransaction(): bool;

    /**
     * Transaction'ı commit et
     *
     * @return bool Commit başarılı mı?
     */
    public function commit(): bool;

    /**
     * Transaction'ı rollback et
     *
     * Hata durumunda tüm değişiklikler geri alınır.
     *
     * @return bool Rollback başarılı mı?
     */
    public function rollback(): bool;

    /**
     * Transaction içinde mi?
     *
     * @return bool
     */
    public function inTransaction(): bool;

    /**
     * Bağlantıyı yeniden aç
     *
     * "MySQL server has gone away" hatası durumunda kullanılır.
     *
     * @return void
     */
    public function reconnect(): void;

    /**
     * Bağlantıyı kapat
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Database name'i al
     *
     * @return string
     */
    public function getDatabaseName(): string;

    /**
     * Table prefix'i al
     *
     * @return string
     */
    public function getTablePrefix(): string;

    /**
     * Driver name'i al (mysql, sqlite, pgsql)
     *
     * @return string
     */
    public function getDriverName(): string;
}
