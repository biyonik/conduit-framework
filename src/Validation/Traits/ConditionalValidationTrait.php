<?php

declare(strict_types=1);

namespace Conduit\Validation\Traits;

/**
 * ConditionalValidationTrait
 * 
 * Provides conditional validation functionality.
 * 
 * @package Conduit\Validation\Traits
 */
trait ConditionalValidationTrait
{
    /**
     * Validate when another field has a specific value
     * 
     * @param mixed $value Value to validate
     * @param array<string, mixed> $data All form data
     * @param string $field Field to check
     * @param mixed $expectedValue Expected value
     * @param callable $validator Validator function
     * @return bool
     */
    protected function validateWhen(
        mixed $value,
        array $data,
        string $field,
        mixed $expectedValue,
        callable $validator
    ): bool {
        if (!isset($data[$field]) || $data[$field] !== $expectedValue) {
            return true; // Skip validation
        }

        return $validator($value);
    }

    /**
     * Validate unless another field has a specific value
     * 
     * @param mixed $value Value to validate
     * @param array<string, mixed> $data All form data
     * @param string $field Field to check
     * @param mixed $excludeValue Value to exclude
     * @param callable $validator Validator function
     * @return bool
     */
    protected function validateUnless(
        mixed $value,
        array $data,
        string $field,
        mixed $excludeValue,
        callable $validator
    ): bool {
        if (isset($data[$field]) && $data[$field] === $excludeValue) {
            return true; // Skip validation
        }

        return $validator($value);
    }

    /**
     * Validate if field exists in data
     * 
     * @param mixed $value Value to validate
     * @param array<string, mixed> $data All form data
     * @param string $field Field to check existence
     * @param callable $validator Validator function
     * @return bool
     */
    protected function validateIfExists(
        mixed $value,
        array $data,
        string $field,
        callable $validator
    ): bool {
        if (!array_key_exists($field, $data)) {
            return true; // Skip validation
        }

        return $validator($value);
    }

    /**
     * Validate with custom condition
     * 
     * @param mixed $value Value to validate
     * @param callable $condition Condition function (returns bool)
     * @param callable $validator Validator function
     * @return bool
     */
    protected function validateIf(
        mixed $value,
        callable $condition,
        callable $validator
    ): bool {
        if (!$condition()) {
            return true; // Skip validation
        }

        return $validator($value);
    }
}
