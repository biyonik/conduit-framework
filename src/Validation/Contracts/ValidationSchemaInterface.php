<?php

declare(strict_types=1);

namespace Conduit\Validation\Contracts;

/**
 * ValidationSchemaInterface
 * 
 * Contract for validation schema builders.
 * Defines the interface for creating validation schemas.
 * 
 * @package Conduit\Validation\Contracts
 */
interface ValidationSchemaInterface
{
    /**
     * Add a validation rule to the schema
     * 
     * @param string $field Field name
     * @param ValidationTypeInterface $type Validation type
     * @return self
     */
    public function field(string $field, ValidationTypeInterface $type): self;

    /**
     * Validate data against the schema
     * 
     * @param array<string, mixed> $data Data to validate
     * @return ValidationResultInterface
     */
    public function validate(array $data): ValidationResultInterface;

    /**
     * Get all registered fields
     * 
     * @return array<string, ValidationTypeInterface>
     */
    public function getFields(): array;

    /**
     * Check if a field is registered
     * 
     * @param string $field Field name
     * @return bool
     */
    public function hasField(string $field): bool;
}
