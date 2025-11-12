<?php

declare(strict_types=1);

namespace Conduit\Database\Exceptions;

/**
 * Transaction Exception
 *
 * Transaction işlem hatası (nested transaction, commit/rollback fail, etc.)
 */
class TransactionException extends DatabaseException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Transaction error: {$message}", $code, $previous);
    }
}
