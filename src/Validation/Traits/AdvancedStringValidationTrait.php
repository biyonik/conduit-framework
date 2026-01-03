<?php

declare(strict_types=1);

namespace Conduit\Validation\Traits;

/**
 * AdvancedStringValidationTrait
 * 
 * Provides advanced string validation functionality.
 * 
 * @package Conduit\Validation\Traits
 */
trait AdvancedStringValidationTrait
{
    /**
     * Validate slug format
     * 
     * @param string $value Value to validate
     * @return bool
     */
    protected function isValidSlug(string $value): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
    }

    /**
     * Validate username format
     * 
     * @param string $value Value to validate
     * @param int $minLength Minimum length
     * @param int $maxLength Maximum length
     * @return bool
     */
    protected function isValidUsername(string $value, int $minLength = 3, int $maxLength = 20): bool
    {
        $length = strlen($value);
        if ($length < $minLength || $length > $maxLength) {
            return false;
        }

        // Alphanumeric, underscore, hyphen only
        return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $value);
    }

    /**
     * Check if string is alphanumeric
     * 
     * @param string $value Value to check
     * @return bool
     */
    protected function isAlphanumeric(string $value): bool
    {
        return ctype_alnum($value);
    }

    /**
     * Check if string contains only alphabetic characters
     * 
     * @param string $value Value to check
     * @return bool
     */
    protected function isAlpha(string $value): bool
    {
        return ctype_alpha($value);
    }

    /**
     * Check if string is a valid hex color
     * 
     * @param string $value Value to check
     * @return bool
     */
    protected function isHexColor(string $value): bool
    {
        return (bool) preg_match('/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $value);
    }

    /**
     * Check if string is a valid JSON
     * 
     * @param string $value Value to check
     * @return bool
     */
    protected function isJson(string $value): bool
    {
        if (trim($value) === '') {
            return false;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Convert string to slug
     * 
     * @param string $value String to convert
     * @param string $separator Separator character
     * @return string
     */
    protected function toSlug(string $value, string $separator = '-'): string
    {
        // Convert to lowercase
        $value = strtolower($value);

        // Replace non-alphanumeric characters with separator
        $value = preg_replace('/[^a-z0-9]+/', $separator, $value);

        // Remove leading/trailing separators
        $value = trim($value, $separator);

        return $value;
    }
}
