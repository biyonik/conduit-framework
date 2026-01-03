<?php

declare(strict_types=1);

namespace Conduit\Validation\Contracts;

/**
 * ValidationResultInterface
 * 
 * Contract for validation results.
 * Contains validation status, errors, and validated data.
 * 
 * @package Conduit\Validation\Contracts
 */
interface ValidationResultInterface
{
    /**
     * Check if validation passed
     * 
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Check if validation failed
     * 
     * @return bool
     */
    public function fails(): bool;

    /**
     * Get all validation errors
     * 
     * @return array<string, array<string>> Field name => array of error messages
     */
    public function getErrors(): array;

    /**
     * Get errors for a specific field
     * 
     * @param string $field Field name
     * @return array<string>
     */
    public function getFieldErrors(string $field): array;

    /**
     * Check if a field has errors
     * 
     * @param string $field Field name
     * @return bool
     */
    public function hasError(string $field): bool;

    /**
     * Get validated data (only fields that passed validation)
     * 
     * @return array<string, mixed>
     */
    public function getValidatedData(): array;

    /**
     * Get first error message
     * 
     * @return string|null
     */
    public function getFirstError(): ?string;

    /**
     * Get all error messages as flat array
     * 
     * @return array<string>
     */
    public function getAllErrorMessages(): array;
}
