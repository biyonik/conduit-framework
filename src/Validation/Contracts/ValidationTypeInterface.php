<?php

declare(strict_types=1);

namespace Conduit\Validation\Contracts;

/**
 * ValidationTypeInterface
 * 
 * Contract for validation types (string, number, boolean, etc.)
 * Each type defines its own validation logic and rules.
 * 
 * @package Conduit\Validation\Contracts
 */
interface ValidationTypeInterface
{
    /**
     * Validate a value
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name (for error messages)
     * @return array<string> Array of error messages (empty if valid)
     */
    public function validate(mixed $value, string $field): array;

    /**
     * Check if the field is required
     * 
     * @return bool
     */
    public function isRequired(): bool;

    /**
     * Set field as required
     * 
     * @return self
     */
    public function required(): self;

    /**
     * Set field as optional
     * 
     * @return self
     */
    public function optional(): self;

    /**
     * Set custom error message
     * 
     * @param string $message Error message
     * @return self
     */
    public function message(string $message): self;

    /**
     * Get the type name
     * 
     * @return string
     */
    public function getTypeName(): string;
}
