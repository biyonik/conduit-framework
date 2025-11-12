<?php

declare(strict_types=1);

namespace Conduit\Database\Schema;

use Conduit\Database\Connection;

/**
 * Migrator - Migration'ları çalıştıran ve rollback eden sınıf
 *
 * CLI commands bu sınıfı kullanacak:
 * - php conduit migrate
 * - php conduit migrate:rollback
 * - php conduit migrate:reset
 * - php conduit migrate:fresh
 *
 * @package Conduit\Database\Schema
 */
class Migrator
{
    /**
     * Migration repository
     */
    protected MigrationRepository $repository;

    /**
     * Connection instance
     */
    protected Connection $connection;

    /**
     * Migration files path
     */
    protected string $path;

    /**
     * Output callback (CLI için)
     */
    protected ?\Closure $output = null;

    /**
     * Constructor
     *
     * @param Connection $connection Database connection
     * @param MigrationRepository $repository Migration repository
     * @param string $path Migration files path
     */
    public function __construct(
        Connection $connection,
        MigrationRepository $repository,
        string $path
    ) {
        $this->connection = $connection;
        $this->repository = $repository;
        $this->path = $path;
    }

    /**
     * Run pending migrations
     *
     * @return array<string> Çalıştırılan migration'lar
     */
    public function run(): array
    {
        // Repository table yoksa oluştur
        if (!$this->repository->repositoryExists()) {
            $this->repository->createRepository();
            $this->note('Migration table created successfully.');
        }

        // Pending migration'ları al
        $pending = $this->repository->getPending($this->path);

        if (empty($pending)) {
            $this->note('Nothing to migrate.');
            return [];
        }

        // Yeni batch numarası
        $batch = $this->repository->getNextBatchNumber();

        $this->note("Running batch #{$batch}...");

        $ran = [];

        foreach ($pending as $file) {
            $this->runMigration($file, $batch);
            $ran[] = $file;
            $this->note("Migrated: {$file}");
        }

        return $ran;
    }

    /**
     * Run a single migration
     *
     * @param string $file Migration dosya adı
     * @param int $batch Batch numarası
     * @return void
     * @throws \RuntimeException Migration çalışmazsa
     */
    protected function runMigration(string $file, int $batch): void
    {
        // Migration dosyasını require et
        $migration = $this->resolve($file);

        // Transaction içinde çalıştır
        $this->connection->beginTransaction();

        try {
            // up() metodunu çalıştır
            $migration->up();

            // Log'a kaydet
            $this->repository->log($file, $batch);

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw new \RuntimeException(
                "Migration failed: {$file}\nError: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Rollback last batch
     *
     * @param int $steps Kaç batch geri gidilecek (default: 1)
     * @return array<string> Rollback edilen migration'lar
     */
    public function rollback(int $steps = 1): array
    {
        $rolled = [];

        for ($i = 0; $i < $steps; $i++) {
            // Son batch'i al
            $migrations = $this->repository->getLastBatch();

            if (empty($migrations)) {
                $this->note('Nothing to rollback.');
                break;
            }

            $batch = $this->repository->getLastBatchNumber();
            $this->note("Rolling back batch #{$batch}...");

            foreach ($migrations as $file) {
                $this->rollbackMigration($file);
                $rolled[] = $file;
                $this->note("Rolled back: {$file}");
            }
        }

        return $rolled;
    }

    /**
     * Rollback a single migration
     *
     * @param string $file Migration dosya adı
     * @return void
     * @throws \RuntimeException Rollback başarısızsa
     */
    protected function rollbackMigration(string $file): void
    {
        // Migration dosyasını require et
        $migration = $this->resolve($file);

        // Transaction içinde çalıştır
        $this->connection->beginTransaction();

        try {
            // down() metodunu çalıştır
            $migration->down();

            // Log'dan sil
            $this->repository->delete($file);

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw new \RuntimeException(
                "Rollback failed: {$file}\nError: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Rollback all migrations
     *
     * @return array<string> Rollback edilen migration'lar
     */
    public function reset(): array
    {
        if (!$this->repository->repositoryExists()) {
            $this->note('Migration table does not exist.');
            return [];
        }

        $rolled = [];
        $step = 1;

        $this->note('Rolling back all migrations...');

        while ($migrations = $this->repository->getLastBatch()) {
            $batch = $this->repository->getLastBatchNumber();
            $this->note("Rolling back batch #{$batch}...");

            foreach ($migrations as $file) {
                $this->rollbackMigration($file);
                $rolled[] = $file;
                $this->note("Rolled back: {$file}");
            }

            $step++;

            // Sonsuz loop önleme (safety check)
            if ($step > 1000) {
                throw new \RuntimeException('Too many rollback steps. Possible infinite loop.');
            }
        }

        return $rolled;
    }

    /**
     * Drop all tables and re-run all migrations
     *
     * @return array<string> Çalıştırılan migration'lar
     */
    public function fresh(): array
    {
        $this->note('Dropping all tables...');

        // Tüm migration'ları rollback et
        $this->reset();

        // Migration tablosunu da sil
        if ($this->repository->repositoryExists()) {
            Schema::dropIfExists($this->repository->getTable());
            $this->note('Migration table dropped.');
        }

        $this->note('Running all migrations...');

        // Tüm migration'ları yeniden çalıştır
        return $this->run();
    }

    /**
     * Get migration status
     *
     * @return array<array{name: string, batch: int|null, ran: bool}>
     */
    public function status(): array
    {
        if (!$this->repository->repositoryExists()) {
            return [];
        }

        $ran = $this->repository->getAllMigrations();
        $all = $this->repository->getMigrations($this->path);

        $ranMap = [];
        foreach ($ran as $migration) {
            $ranMap[$migration['migration']] = (int) $migration['batch'];
        }

        $status = [];

        foreach ($all as $migration) {
            $status[] = [
                'name' => $migration,
                'batch' => $ranMap[$migration] ?? null,
                'ran' => isset($ranMap[$migration]),
            ];
        }

        return $status;
    }

    /**
     * Get pending migrations count
     *
     * @return int
     */
    public function getPendingCount(): int
    {
        if (!$this->repository->repositoryExists()) {
            return count($this->repository->getMigrations($this->path));
        }

        return count($this->repository->getPending($this->path));
    }

    /**
     * Resolve migration instance from file
     *
     * @param string $file Migration dosya adı (uzantısız)
     * @return Migration
     * @throws \RuntimeException Dosya bulunamazsa veya geçersiz migration
     */
    protected function resolve(string $file): Migration
    {
        $path = $this->path . '/' . $file . '.php';

        if (!file_exists($path)) {
            throw new \RuntimeException("Migration file not found: {$path}");
        }

        $migration = require $path;

        if (!$migration instanceof Migration) {
            throw new \RuntimeException(
                "Migration file must return a Migration instance: {$file}\n" .
                "Expected: return new class extends Migration { ... }"
            );
        }

        return $migration;
    }

    /**
     * Set output callback (CLI için)
     *
     * @param \Closure $callback function(string $message): void
     * @return void
     */
    public function setOutput(\Closure $callback): void
    {
        $this->output = $callback;
    }

    /**
     * Output a note/message
     *
     * @param string $message
     * @return void
     */
    protected function note(string $message): void
    {
        if ($this->output) {
            ($this->output)($message);
        }
    }

    /**
     * Get repository
     *
     * @return MigrationRepository
     */
    public function getRepository(): MigrationRepository
    {
        return $this->repository;
    }

    /**
     * Get migration path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Set migration path
     *
     * @param string $path
     * @return void
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }
}