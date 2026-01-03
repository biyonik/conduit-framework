<?php

declare(strict_types=1);

namespace Conduit\Validation\Traits;

/**
 * AdvancedValidationTrait
 * 
 * Provides advanced validation rules and utilities.
 * 
 * @package Conduit\Validation\Traits
 */
trait AdvancedValidationTrait
{
    /**
     * Validate that field matches another field
     * 
     * @param mixed $value Value to validate
     * @param array<string, mixed> $data All form data
     * @param string $otherField Other field name
     * @return bool
     */
    protected function validateSame(mixed $value, array $data, string $otherField): bool
    {
        return isset($data[$otherField]) && $value === $data[$otherField];
    }

    /**
     * Validate that field is different from another field
     * 
     * @param mixed $value Value to validate
     * @param array<string, mixed> $data All form data
     * @param string $otherField Other field name
     * @return bool
     */
    protected function validateDifferent(mixed $value, array $data, string $otherField): bool
    {
        return !isset($data[$otherField]) || $value !== $data[$otherField];
    }

    /**
     * Validate that value is in array
     * 
     * @param mixed $value Value to validate
     * @param array<mixed> $allowed Allowed values
     * @return bool
     */
    protected function validateIn(mixed $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    /**
     * Validate that value is not in array
     * 
     * @param mixed $value Value to validate
     * @param array<mixed> $forbidden Forbidden values
     * @return bool
     */
    protected function validateNotIn(mixed $value, array $forbidden): bool
    {
        return !in_array($value, $forbidden, true);
    }

    /**
     * Validate file extension
     * 
     * @param string $filename Filename
     * @param array<string> $allowedExtensions Allowed extensions
     * @return bool
     */
    protected function validateFileExtension(string $filename, array $allowedExtensions): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, array_map('strtolower', $allowedExtensions), true);
    }

    /**
     * Validate MIME type
     * 
     * @param string $filepath File path
     * @param array<string> $allowedMimes Allowed MIME types
     * @return bool
     */
    protected function validateMimeType(string $filepath, array $allowedMimes): bool
    {
        if (!file_exists($filepath)) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filepath);
        finfo_close($finfo);

        return in_array($mime, $allowedMimes, true);
    }

    /**
     * Validate array has specific keys
     * 
     * @param array<mixed> $array Array to validate
     * @param array<string> $requiredKeys Required keys
     * @return bool
     */
    protected function validateArrayHasKeys(array $array, array $requiredKeys): bool
    {
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate unique values in array
     * 
     * @param array<mixed> $array Array to validate
     * @return bool
     */
    protected function validateUniqueArray(array $array): bool
    {
        return count($array) === count(array_unique($array, SORT_REGULAR));
    }
}
