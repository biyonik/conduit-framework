<?php

declare(strict_types=1);

namespace Conduit\Database\Schema;

/**
 * Base Migration Class
 * 
 * Tüm migration'lar bu sınıfı extend eder.
 * up() ve down() metotları implement edilmeli.
 * 
 * @package Conduit\Database\Schema
 */
abstract class Migration
{
    /**
     * Run the migration (create/alter tables)
     * 
     * Bu metod migration çalıştırıldığında (php conduit migrate) execute edilir
     * 
     * @return void
     */
    abstract public function up(): void;

    /**
     * Reverse the migration (rollback)
     * 
     * Bu metod migration geri alındığında (php conduit migrate:rollback) execute edilir
     * 
     * @return void
     */
    abstract public function down(): void;

    /**
     * Run the given migration method
     * 
     * @param string $method 'up' veya 'down'
     * @return void
     * @throws \InvalidArgumentException Geçersiz method adı
     */
    public function run(string $method): void
    {
        if (!in_array($method, ['up', 'down'])) {
            throw new \InvalidArgumentException("Invalid migration method: {$method}");
        }

        $this->$method();
    }

    /**
     * Get the migration name from class name
     * 
     * @return string Migration adı
     */
    public function getName(): string
    {
        return static::class;
    }
}