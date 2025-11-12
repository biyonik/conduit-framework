<?php

declare(strict_types=1);

namespace Conduit\Database\Exceptions;

/**
 * Migration Exception
 *
 * Migration çalıştırma hatası
 */
class MigrationException extends DatabaseException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Migration error: {$message}", $code, $previous);
    }
}
