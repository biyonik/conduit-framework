<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

/**
 * StringType
 * 
 * String validation type.
 * Validates string values with length and pattern constraints.
 * 
 * @package Conduit\Validation\SchemaType
 */
class StringType extends BaseType
{
    protected string $typeName = 'string';
    
    protected ?int $minLength = null;
    protected ?int $maxLength = null;
    protected ?string $pattern = null;
    protected ?array $enum = null;

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

        // Min length
        if ($this->minLength !== null && mb_strlen($value) < $this->minLength) {
            $errors[] = $this->getErrorMessage(
                $field,
                "The {$field} must be at least {$this->minLength} characters."
            );
        }

        // Max length
        if ($this->maxLength !== null && mb_strlen($value) > $this->maxLength) {
            $errors[] = $this->getErrorMessage(
                $field,
                "The {$field} must not exceed {$this->maxLength} characters."
            );
        }

        // Pattern
        if ($this->pattern !== null && !preg_match($this->pattern, $value)) {
            $errors[] = $this->getErrorMessage($field, "The {$field} format is invalid.");
        }

        // Enum
        if ($this->enum !== null && !in_array($value, $this->enum, true)) {
            $errors[] = $this->getErrorMessage(
                $field,
                "The {$field} must be one of: " . implode(', ', $this->enum)
            );
        }

        return $errors;
    }

    /**
     * Set minimum length
     * 
     * @param int $length Minimum length
     * @return self
     */
    public function min(int $length): self
    {
        $this->minLength = $length;
        return $this;
    }

    /**
     * Set maximum length
     * 
     * @param int $length Maximum length
     * @return self
     */
    public function max(int $length): self
    {
        $this->maxLength = $length;
        return $this;
    }

    /**
     * Set length (exact or range)
     * 
     * @param int $min Minimum length
     * @param int|null $max Maximum length (optional)
     * @return self
     */
    public function length(int $min, ?int $max = null): self
    {
        $this->minLength = $min;
        $this->maxLength = $max ?? $min;
        return $this;
    }

    /**
     * Set regex pattern
     * 
     * @param string $pattern Regular expression pattern
     * @return self
     */
    public function pattern(string $pattern): self
    {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * Set enum values
     * 
     * @param array<string> $values Allowed values
     * @return self
     */
    public function enum(array $values): self
    {
        $this->enum = $values;
        return $this;
    }

    /**
     * Email validation
     * 
     * @return self
     */
    public function email(): self
    {
        $this->pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        return $this;
    }

    /**
     * URL validation
     * 
     * @return self
     */
    public function url(): self
    {
        $this->pattern = '/^https?:\/\/.+/';
        return $this;
    }
}
