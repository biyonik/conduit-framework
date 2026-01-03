<?php

declare(strict_types=1);

namespace Conduit\Validation;

use Conduit\Validation\Contracts\ValidationResultInterface;

/**
 * ValidationResult
 * 
 * Holds the results of a validation operation.
 * Contains errors and validated data.
 * 
 * @package Conduit\Validation
 */
class ValidationResult implements ValidationResultInterface
{
    /**
     * Validation errors
     * 
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * Validated data
     * 
     * @var array<string, mixed>
     */
    protected array $validatedData = [];

    /**
     * Constructor
     * 
     * @param array<string, array<string>> $errors Validation errors
     * @param array<string, mixed> $validatedData Validated data
     */
    public function __construct(array $errors = [], array $validatedData = [])
    {
        $this->errors = $errors;
        $this->validatedData = $validatedData;
    }

    /**
     * {@inheritDoc}
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * {@inheritDoc}
     */
    public function fails(): bool
    {
        return !$this->isValid();
    }

    /**
     * {@inheritDoc}
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * {@inheritDoc}
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * {@inheritDoc}
     */
    public function getValidatedData(): array
    {
        return $this->validatedData;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function getAllErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        return $messages;
    }

    /**
     * Add an error for a field
     * 
     * @param string $field Field name
     * @param string $error Error message
     * @return void
     */
    public function addError(string $field, string $error): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $error;
    }

    /**
     * Add validated data for a field
     * 
     * @param string $field Field name
     * @param mixed $value Validated value
     * @return void
     */
    public function addValidatedData(string $field, mixed $value): void
    {
        $this->validatedData[$field] = $value;
    }
}
