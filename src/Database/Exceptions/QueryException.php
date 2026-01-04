<?php

declare(strict_types=1);

namespace Conduit\Database\Exceptions;

/**
 * Query Exception
 *
 * SQL query hatası (syntax error, constraint violation, etc.)
 */
class QueryException extends DatabaseException
{
    public function __construct(
        string $message,
        string $sql = '',
        array $bindings = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->sql = $sql;
        $this->bindings = $bindings;
    }
}
