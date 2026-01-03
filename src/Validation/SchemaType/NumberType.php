<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

/**
 * NumberType
 * 
 * Number validation type.
 * Validates integer and float values with range constraints.
 * 
 * @package Conduit\Validation\SchemaType
 */
class NumberType extends BaseType
{
    protected string $typeName = 'number';
    
    protected ?float $min = null;
    protected ?float $max = null;
    protected bool $integerOnly = false;
    protected bool $positiveOnly = false;

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

        // Check if value is numeric
        if (!is_numeric($value)) {
            $errors[] = $this->getErrorMessage($field, "The {$field} must be a number.");
            return $errors;
        }

        $numValue = (float) $value;

        // Integer only
        if ($this->integerOnly && !is_int($value) && (int) $value != $value) {
            $errors[] = $this->getErrorMessage($field, "The {$field} must be an integer.");
        }

        // Positive only
        if ($this->positiveOnly && $numValue < 0) {
            $errors[] = $this->getErrorMessage($field, "The {$field} must be positive.");
        }

        // Min value
        if ($this->min !== null && $numValue < $this->min) {
            $errors[] = $this->getErrorMessage(
                $field,
                "The {$field} must be at least {$this->min}."
            );
        }

        // Max value
        if ($this->max !== null && $numValue > $this->max) {
            $errors[] = $this->getErrorMessage(
                $field,
                "The {$field} must not exceed {$this->max}."
            );
        }

        return $errors;
    }

    /**
     * Set minimum value
     * 
     * @param float $value Minimum value
     * @return self
     */
    public function min(float $value): self
    {
        $this->min = $value;
        return $this;
    }

    /**
     * Set maximum value
     * 
     * @param float $value Maximum value
     * @return self
     */
    public function max(float $value): self
    {
        $this->max = $value;
        return $this;
    }

    /**
     * Set value range
     * 
     * @param float $min Minimum value
     * @param float $max Maximum value
     * @return self
     */
    public function range(float $min, float $max): self
    {
        $this->min = $min;
        $this->max = $max;
        return $this;
    }

    /**
     * Require integer values only
     * 
     * @return self
     */
    public function integer(): self
    {
        $this->integerOnly = true;
        return $this;
    }

    /**
     * Require positive values only
     * 
     * @return self
     */
    public function positive(): self
    {
        $this->positiveOnly = true;
        return $this;
    }
}
