<?php

declare(strict_types=1);

namespace Conduit\Database;

use Conduit\Database\Contracts\ConnectionInterface;
use Conduit\Database\Exceptions\ConnectionException;
use Conduit\Database\Exceptions\DatabaseException;
use Conduit\Database\Exceptions\QueryException;
use Conduit\Database\Grammar\Grammar;
use Conduit\Database\Grammar\MySQLGrammar;
use Conduit\Database\Grammar\PostgreSQLGrammar;
use Conduit\Database\Grammar\SQLiteGrammar;
use PDO;
use PDOException;

/**
 * Database Connection
 *
 * PDO wrapper sınıfı. SQL injection koruması, reconnection,
 * transaction desteği sağlar.
 *
 * @package Conduit\Database
 */
class Connection implements ConnectionInterface
{
    /**
     * PDO instance (lazy loading)
     */
    protected ?PDO $pdo = null;

    /**
     * Active transaction count (nested transaction desteği)
     */
    protected int $transactionLevel = 0;

    /**
     * Constructor
     *
     * @param array $config Connection config
     */
    public function __construct(
        protected array $config
    ) {}

    /**
     * {@inheritdoc}
     * @throws ConnectionException
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Database bağlantısını aç
     *
     * @return void
     * @throws ConnectionException
     */
    protected function connect(): void
    {
        try {
            $this->pdo = new PDO(
                $this->getDsn(),
                $this->config['username'] ?? null,
                $this->config['password'] ?? null,
                $this->getDefaultOptions()
            );
        } catch (PDOException $e) {
            throw new ConnectionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * DSN string oluştur
     *
     * @return string
     * @throws ConnectionException
     */
    protected function getDsn(): string
    {
        $driver = $this->config['driver'] ?? 'mysql';

        return match ($driver) {
            'mysql' => $this->getMySqlDsn(),
            'sqlite' => $this->getSqliteDsn(),
            'pgsql' => $this->getPostgresDsn(),
            default => throw new ConnectionException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * MySQL DSN
     *
     * @return string
     */
    protected function getMySqlDsn(): string
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 3306;
        $database = $this->config['database'];
        $charset = $this->config['charset'] ?? 'utf8mb4';

        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }

    /**
     * SQLite DSN
     *
     * @return string
     */
    protected function getSqliteDsn(): string
    {
        $database = $this->config['database'];

        // In-memory database
        if ($database === ':memory:') {
            return 'sqlite::memory:';
        }

        return "sqlite:{$database}";
    }

    /**
     * PostgreSQL DSN
     *
     * @return string
     */
    protected function getPostgresDsn(): string
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 5432;
        $database = $this->config['database'];

        return "pgsql:host={$host};port={$port};dbname={$database}";
    }

    /**
     * PDO default options
     *
     * @return array
     */
    protected function getDefaultOptions(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Real prepared statements
        ];

        // Merge custom options
        if (isset($this->config['options'])) {
            $options = array_replace($options, $this->config['options']);
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     * @throws QueryException|ConnectionException
     */
    public function statement(string $query, array $bindings = []): bool
    {
        try {
            return $this->getPdo()->prepare($query)->execute($bindings);
        } catch (PDOException $e) {
            throw $this->createQueryException($e, $query, $bindings);
        }
    }

    /**
     * {@inheritdoc}
     * @throws QueryException
     * @throws ConnectionException
     */
    public function select(string $query, array $bindings = []): array
    {
        try {
            $statement = $this->getPdo()->prepare($query);
            $statement->execute($bindings);
            return $statement->fetchAll();
        } catch (PDOException $e) {
            throw $this->createQueryException($e, $query, $bindings);
        }
    }

    /**
     * {@inheritdoc}
     * @throws QueryException
     * @throws ConnectionException
     */
    public function insert(string $query, array $bindings = []): int
    {
        try {
            $statement = $this->getPdo()->prepare($query);
            $statement->execute($bindings);
            return (int) $this->getPdo()->lastInsertId();
        } catch (PDOException $e) {
            throw $this->createQueryException($e, $query, $bindings);
        }
    }

    /**
     * {@inheritdoc}
     * @throws QueryException
     * @throws ConnectionException
     */
    public function update(string $query, array $bindings = []): int
    {
        try {
            $statement = $this->getPdo()->prepare($query);
            $statement->execute($bindings);
            return $statement->rowCount();
        } catch (PDOException $e) {
            throw $this->createQueryException($e, $query, $bindings);
        }
    }

    /**
     * {@inheritdoc}
     * @throws DatabaseException
     */
    public function delete(string $query, array $bindings = []): int
    {
        try {
            return $this->update($query, $bindings);
        } catch (ConnectionException|QueryException $e) {
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     * @throws ConnectionException
     */
    public function beginTransaction(): bool
    {
        // Nested transaction desteği (SAVEPOINT kullanarak)
        if ($this->transactionLevel === 0) {
            try {
                return $this->getPdo()->beginTransaction();
            } catch (PDOException $e) {
                throw new ConnectionException("Failed to begin transaction: {$e->getMessage()}", 0, $e);
            } finally {
                $this->transactionLevel++;
            }
        }

        // Nested transaction için SAVEPOINT kullan
        $this->getPdo()->exec("SAVEPOINT trans{$this->transactionLevel}");
        $this->transactionLevel++;

        return true;
    }

    /**
     * {@inheritdoc}
     * @throws ConnectionException
     */
    public function commit(): bool
    {
        if ($this->transactionLevel === 0) {
            return false;
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            try {
                return $this->getPdo()->commit();
            } catch (PDOException $e) {
                throw new ConnectionException("Failed to commit transaction: {$e->getMessage()}", 0, $e);
            }
        }

        // Nested transaction için SAVEPOINT release
        $this->getPdo()->exec("RELEASE SAVEPOINT trans{$this->transactionLevel}");

        return true;
    }

    /**
     * {@inheritdoc}
     * @throws ConnectionException
     */
    public function rollback(): bool
    {
        if ($this->transactionLevel === 0) {
            return false;
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            try {
                return $this->getPdo()->rollBack();
            } catch (PDOException $e) {
                throw new ConnectionException("Failed to rollback transaction: {$e->getMessage()}", 0, $e);
            }
        }

        // Nested transaction için SAVEPOINT rollback
        $this->getPdo()->exec("ROLLBACK TO SAVEPOINT trans{$this->transactionLevel}");

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    /**
     * {@inheritdoc}
     * @throws ConnectionException
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->transactionLevel = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseName(): string
    {
        return $this->config['database'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getTablePrefix(): string
    {
        return $this->config['prefix'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName(): string
    {
        return $this->config['driver'] ?? 'mysql';
    }

    public function getGrammar(): Grammar
    {
        $driver = $this->getDriverName();

        return match ($driver) {
            'mysql' => new MySQLGrammar(),
            'sqlite' => new SQLiteGrammar(),
            'pgsql' => new PostgreSQLGrammar(),
            default => throw new ConnectionException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * QueryException oluştur
     *
     * @param PDOException $e PDO hatası
     * @param string $sql SQL query
     * @param array $bindings Bound values
     * @return QueryException
     */
    protected function createQueryException(PDOException $e, string $sql, array $bindings): QueryException
    {
        return new QueryException(
            $e->getMessage(),
            $sql,
            $bindings,
            (int) $e->getCode(),
            $e
        );
    }
}
