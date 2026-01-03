<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

use Conduit\Validation\Contracts\ValidationSchemaInterface;

/**
 * ObjectType
 * 
 * Object validation type.
 * Validates object/associative array values with nested schema.
 * 
 * @package Conduit\Validation\SchemaType
 */
class ObjectType extends BaseType
{
    protected string $typeName = 'object';
    
    protected ?ValidationSchemaInterface $schema = null;
    protected bool $strict = false;

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

        // Check if value is array (object representation)
        if (!is_array($value)) {
            $errors[] = $this->getErrorMessage($field, "The {$field} must be an object.");
            return $errors;
        }

        // Validate with nested schema if provided
        if ($this->schema !== null) {
            $result = $this->schema->validate($value);
            if ($result->fails()) {
                foreach ($result->getErrors() as $nestedField => $nestedErrors) {
                    foreach ($nestedErrors as $error) {
                        $errors[] = str_replace($nestedField, "{$field}.{$nestedField}", $error);
                    }
                }
            }
        }

        // Strict mode: no additional properties allowed
        if ($this->strict && $this->schema !== null) {
            $allowedKeys = array_keys($this->schema->getFields());
            $extraKeys = array_diff(array_keys($value), $allowedKeys);
            if (!empty($extraKeys)) {
                $errors[] = $this->getErrorMessage(
                    $field,
                    "The {$field} contains unexpected properties: " . implode(', ', $extraKeys)
                );
            }
        }

        return $errors;
    }

    /**
     * Set nested schema
     * 
     * @param ValidationSchemaInterface $schema Nested schema
     * @return self
     */
    public function schema(ValidationSchemaInterface $schema): self
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * Enable strict mode (no additional properties)
     * 
     * @return self
     */
    public function strict(): self
    {
        $this->strict = true;
        return $this;
    }
}
