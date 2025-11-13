<?php

declare(strict_types=1);

namespace Conduit\Database\Schema;

use Conduit\Database\Connection;
use Conduit\Database\Exceptions\MigrationException;
use PDOException;

/**
 * Database Migration Yöneticisi
 *
 * Sorumluluklar:
 * - Migration dosyalarını çalıştırma
 * - Migration durumunu takip etme (migrations tablosu)
 * - Rollback desteği
 * - Dry-run mode (SQL preview)
 *
 * @package Conduit\Database\Schema
 */
class Migrator
{
    /**
     * Migration repository (durum takibi)
     */
    private MigrationRepository $repository;

    /**
     * Dry-run mode aktif mi?
     */
    private bool $dryRun = false;

    /**
     * Dry-run mode'da toplanan SQL statements
     */
    private array $previewSql = [];

    public function __construct(
        private Connection $connection,
        private string $migrationPath
    ) {
        $this->repository = new MigrationRepository($connection);
    }

    /**
     * Dry-run mode'u aktif et
     *
     * Bu modda hiçbir SQL execute edilmez, sadece preview gösterilir
     */
    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;
        $this->previewSql = [];

        return $this;
    }

    /**
     * Dry-run mode aktif mi?
     */
    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * Preview SQL statements'ları al (dry-run mode için)
     */
    final public function getPreviewSql(): array
    {
        return $this->previewSql;
    }

    /**
     * Bekleyen migration'ları çalıştır
     *
     * @return array Çalıştırılan migration'lar
     * @throws MigrationException
     */
    final public function run(): array
    {
        // Migrations tablosunu oluştur (yoksa)
        if (!$this->dryRun) {
            $this->repository->createRepository();
        }

        // Bekleyen migration'ları bul
        $migrations = $this->getPendingMigrations();

        if (empty($migrations)) {
            return [];
        }

        $batch = $this->repository->getNextBatchNumber();
        $ran = [];

        foreach ($migrations as $migration) {
            if ($this->dryRun) {
                $this->runMigrationDryRun($migration);
            } else {
                $this->runMigration($migration, $batch);
            }

            $ran[] = $migration;
        }

        return $ran;
    }

    /**
     * Migration'ı dry-run mode'da çalıştır (preview only)
     */
    private function runMigrationDryRun(string $file): void
    {
        $migration = $this->resolve($file);

        // Schema builder'ı dry-run mode'a al
        $schema = new Schema($this->connection);
        $schema->setDryRun(true);

        // Migration'ı çalıştır (SQL topla)
        $migration->up();

        // Toplanan SQL'leri al
        $statements = $schema->getPreviewSql();

        $this->previewSql[$file] = $statements;
    }

    /**
     * Migration'ı gerçekten çalıştır
     */
    private function runMigration(string $file, int $batch): void
    {
        $migration = $this->resolve($file);

        try {
            $this->connection->beginTransaction();

            // Migration'ı çalıştır
            $migration->up();

            // Başarılı ise kaydet
            $this->repository->log($file, $batch);

            $this->connection->commit();

        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw new MigrationException(
                "Migration failed: {$file}. Error: " . $e->getMessage()
            );
        }
    }

    /**
     * Bekleyen migration'ları bul
     *
     * @return array Migration dosya isimleri
     */
    private function getPendingMigrations(): array
    {
        // Çalıştırılmış migration'lar
        $ran = $this->repository->getRan();

        // Migration dosyalarını tara
        $files = $this->getMigrationFiles();

        // Henüz çalıştırılmamış olanlar
        return array_diff($files, $ran);
    }

    /**
     * Migration dosyalarını getir (sıralı)
     */
    private function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationPath)) {
            return [];
        }

        $files = glob($this->migrationPath . '/*.php');

        if ($files === false) {
            return [];
        }

        // Sadece dosya adları
        $files = array_map(fn($file) => basename($file), $files);

        // Timestamp'e göre sırala
        sort($files);

        return $files;
    }

    /**
     * Migration instance'ı oluştur
     */
    private function resolve(string $file): Migration
    {
        $path = $this->migrationPath . '/' . $file;

        require_once $path;

        // Class adını dosya adından çıkar
        // 2024_01_01_000000_create_users_table.php -> CreateUsersTable
        $className = $this->getClassNameFromFile($file);

        return new $className($this->connection);
    }

    /**
     * Dosya adından class adını çıkar
     */
    private function getClassNameFromFile(string $file): string
    {
        // 2024_01_01_000000_create_users_table.php
        $name = str_replace('.php', '', $file);

        // 2024_01_01_000000_create_users_table -> create_users_table
        $parts = explode('_', $name);
        $name = implode('_', array_slice($parts, 4));

        // create_users_table -> CreateUsersTable
        return str_replace('_', '', ucwords($name, '_'));
    }

    /**
     * Son batch'i rollback et
     *
     * @return array Rollback edilen migration'lar
     */
    public function rollback(): array
    {
        $migrations = $this->repository->getLast();

        if (empty($migrations)) {
            return [];
        }

        $rolledBack = [];

        foreach ($migrations as $migration) {
            if ($this->dryRun) {
                $this->rollbackMigrationDryRun($migration);
            } else {
                $this->rollbackMigration($migration);
            }

            $rolledBack[] = $migration;
        }

        return $rolledBack;
    }

    /**
     * Migration'ı rollback et (dry-run)
     */
    private function rollbackMigrationDryRun(string $file): void
    {
        $migration = $this->resolve($file);

        $schema = new Schema($this->connection);
        $schema->setDryRun(true);

        $migration->down();

        $statements = $schema->getPreviewSql();

        $this->previewSql[$file] = $statements;
    }

    /**
     * Migration'ı rollback et (gerçek)
     */
    private function rollbackMigration(string $file): void
    {
        $migration = $this->resolve($file);

        try {
            $this->connection->beginTransaction();

            $migration->down();

            $this->repository->delete($file);

            $this->connection->commit();

        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw new MigrationException(
                "Rollback failed: {$file}. Error: " . $e->getMessage()
            );
        }
    }

    /**
     * Tüm migration'ları rollback et
     *
     * @return array Rollback edilen migration'lar
     */
    public function reset(): array
    {
        $migrations = array_reverse($this->repository->getRan());

        if (empty($migrations)) {
            return [];
        }

        $rolledBack = [];

        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration;
        }

        return $rolledBack;
    }
}
