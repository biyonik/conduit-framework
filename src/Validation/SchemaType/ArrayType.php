<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

use Conduit\Validation\Contracts\ValidationTypeInterface;

/**
 * ArrayType
 * 
 * Array validation type.
 * Validates array values with size and item constraints.
 * 
 * @package Conduit\Validation\SchemaType
 */
class ArrayType extends BaseType
{
    protected string $typeName = 'array';
    
    protected ?int $minItems = null;
    protected ?int $maxItems = null;
    protected ?ValidationTypeInterface $itemType = null;
    protected bool $uniqueItems = false;

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

        // Check if value is array
        if (!is_array($value)) {
            $errors[] = $this->getErrorMessage($field, "The {$field} must be an array.");
            return $errors;
        }

        // Min items
        if ($this->minItems !== null && count($value) < $this->minItems) {
            $errors[] = $this->getErrorMessage(
                $field,
                "The {$field} must have at least {$this->minItems} items."
            );
        }

        // Max items
        if ($this->maxItems !== null && count($value) > $this->maxItems) {
            $errors[] = $this->getErrorMessage(
                $field,
                "The {$field} must not have more than {$this->maxItems} items."
            );
        }

        // Unique items
        if ($this->uniqueItems && count($value) !== count(array_unique($value, SORT_REGULAR))) {
            $errors[] = $this->getErrorMessage($field, "The {$field} must contain unique items.");
        }

        // Validate each item if item type is specified
        if ($this->itemType !== null) {
            foreach ($value as $index => $item) {
                $itemErrors = $this->itemType->validate($item, "{$field}[{$index}]");
                $errors = array_merge($errors, $itemErrors);
            }
        }

        return $errors;
    }

    /**
     * Set minimum number of items
     * 
     * @param int $count Minimum count
     * @return self
     */
    public function min(int $count): self
    {
        $this->minItems = $count;
        return $this;
    }

    /**
     * Set maximum number of items
     * 
     * @param int $count Maximum count
     * @return self
     */
    public function max(int $count): self
    {
        $this->maxItems = $count;
        return $this;
    }

    /**
     * Set item type validator
     * 
     * @param ValidationTypeInterface $type Item validator
     * @return self
     */
    public function items(ValidationTypeInterface $type): self
    {
        $this->itemType = $type;
        return $this;
    }

    /**
     * Require unique items
     * 
     * @return self
     */
    public function unique(): self
    {
        $this->uniqueItems = true;
        return $this;
    }
}
