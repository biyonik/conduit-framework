<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

use Conduit\Validation\Traits\UuidValidationTrait;

/**
 * UuidType
 * 
 * UUID validation type.
 * Validates UUID v4 format strings.
 * 
 * @package Conduit\Validation\SchemaType
 */
class UuidType extends BaseType
{
    use UuidValidationTrait;

    protected string $typeName = 'uuid';
    
    protected int $version = 4;

    /**
     * {@inheritDoc}
     */
    public function validate(mixed $value, string $field): array
    {
        $errors = [];

        // Skip validation if optional and empty
        if (!$this->isRequired && $this->isEmpty($value)) {
            return $errors;
        }

        // Check if value is string
        if (!is_string($value)) {
            $errors[] = $this->getErrorMessage($field, "The {$field} must be a string.");
            return $errors;
        }

        // Validate UUID format
        if (!$this->isValidUuid($value, $this->version)) {
            $errors[] = $this->getErrorMessage(
                $field,
                "The {$field} must be a valid UUID v{$this->version}."
            );
        }

        return $errors;
    }

    /**
     * Set UUID version
     * 
     * @param int $version UUID version (1-5)
     * @return self
     */
    public function version(int $version): self
    {
        $this->version = $version;
        return $this;
    }
}
