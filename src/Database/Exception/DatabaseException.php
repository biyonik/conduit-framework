<?php

declare(strict_types=1);

namespace Conduit\Database\Exceptions;

use Exception;

/**
 * Base Database Exception
 *
 * Tüm database exception'ları bu sınıftan türer.
 *
 * @package Conduit\Database\Exceptions
 */
class DatabaseException extends Exception
{
    /**
     * SQL query (debugging için)
     */
    protected string $sql = '';

    /**
     * SQL bindings (debugging için)
     */
    protected array $bindings = [];

    /**
     * Set SQL query
     *
     * @param string $sql SQL query
     * @return self
     */
    public function setSql(string $sql): self
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * Get SQL query
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Set SQL bindings
     *
     * @param array $bindings Bound values
     * @return self
     */
    public function setBindings(array $bindings): self
    {
        $this->bindings = $bindings;
        return $this;
    }

    /**
     * Get SQL bindings
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Full error context
     *
     * @return array
     */
    public function getContext(): array
    {
        return [
            'message' => $this->getMessage(),
            'sql' => $this->sql,
            'bindings' => $this->bindings,
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}
