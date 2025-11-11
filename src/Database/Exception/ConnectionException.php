<?php

declare(strict_types=1);

namespace Conduit\Database\Exceptions;

/**
 * Connection Exception
 *
 * Database bağlantı hatası (host unreachable, wrong credentials, etc.)
 */
class ConnectionException extends DatabaseException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Database connection failed: {$message}", $code, $previous);
    }
}
