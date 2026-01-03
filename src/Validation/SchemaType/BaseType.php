<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

use Conduit\Validation\Contracts\ValidationTypeInterface;

/**
 * BaseType
 * 
 * Base class for all validation types.
 * Provides common functionality for validation rules.
 * 
 * @package Conduit\Validation\SchemaType
 */
abstract class BaseType implements ValidationTypeInterface
{
    /**
     * Is field required?
     */
    protected bool $isRequired = false;

    /**
     * Custom error message
     */
    protected ?string $customMessage = null;

    /**
     * Type name
     */
    protected string $typeName = 'base';

    /**
     * {@inheritDoc}
     */
    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    /**
     * {@inheritDoc}
     */
    public function required(): self
    {
        $this->isRequired = true;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function optional(): self
    {
        $this->isRequired = false;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function message(string $message): self
    {
        $this->customMessage = $message;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getTypeName(): string
    {
        return $this->typeName;
    }

    /**
     * Get error message
     * 
     * @param string $field Field name
     * @param string $defaultMessage Default error message
     * @return string
     */
    protected function getErrorMessage(string $field, string $defaultMessage): string
    {
        if ($this->customMessage !== null) {
            return str_replace(':field', $field, $this->customMessage);
        }
        return $defaultMessage;
    }

    /**
     * Check if value is empty
     * 
     * @param mixed $value Value to check
     * @return bool
     */
    protected function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        if (is_array($value) && empty($value)) {
            return true;
        }
        return false;
    }
}
