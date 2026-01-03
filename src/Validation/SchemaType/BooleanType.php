<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

/**
 * BooleanType
 * 
 * Boolean validation type.
 * Validates boolean values with flexible type coercion.
 * 
 * @package Conduit\Validation\SchemaType
 */
class BooleanType extends BaseType
{
    protected string $typeName = 'boolean';
    
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

        // Strict mode: only true/false
        if ($this->strict) {
            if (!is_bool($value)) {
                $errors[] = $this->getErrorMessage($field, "The {$field} must be true or false.");
            }
            return $errors;
        }

        // Flexible mode: accept various boolean representations
        $validTrueValues = [true, 1, '1', 'true', 'yes', 'on'];
        $validFalseValues = [false, 0, '0', 'false', 'no', 'off'];
        
        $lowerValue = is_string($value) ? strtolower($value) : $value;

        if (!in_array($lowerValue, array_merge($validTrueValues, $validFalseValues), true)) {
            $errors[] = $this->getErrorMessage($field, "The {$field} must be a boolean value.");
        }

        return $errors;
    }

    /**
     * Enable strict mode (only true/false)
     * 
     * @return self
     */
    public function strict(): self
    {
        $this->strict = true;
        return $this;
    }
}
