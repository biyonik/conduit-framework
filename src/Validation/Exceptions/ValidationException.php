<?php

declare(strict_types=1);

namespace Conduit\Validation\Exceptions;

use Exception;

/**
 * ValidationException
 * 
 * Exception thrown when validation fails.
 * Contains validation errors for all fields.
 * 
 * @package Conduit\Validation\Exceptions
 */
class ValidationException extends Exception
{
    /**
     * Validation errors
     * 
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param array<string, array<string>> $errors Validation errors
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get validation errors
     * 
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     * 
     * @param string $field Field name
     * @return array<string>
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a field has errors
     * 
     * @param string $field Field name
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get first error message
     * 
     * @return string|null
     */
    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        return null;
    }

    /**
     * Get all error messages as flat array
     * 
     * @return array<string>
     */
    public function getAllErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        return $messages;
    }
}
