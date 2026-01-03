<?php

declare(strict_types=1);

namespace Conduit\Validation\Traits;

/**
 * PhoneValidationTrait
 * 
 * Provides phone number validation functionality.
 * 
 * @package Conduit\Validation\Traits
 */
trait PhoneValidationTrait
{
    /**
     * Validate phone number
     * 
     * @param string $phone Phone number
     * @param string|null $countryCode Country code (optional)
     * @return bool
     */
    protected function isValidPhone(string $phone, ?string $countryCode = null): bool
    {
        // Remove common separators
        $cleaned = preg_replace('/[\s\-\(\)\.]/', '', $phone);

        // Basic validation: starts with + or digit, 7-15 digits total
        if (!preg_match('/^\+?[0-9]{7,15}$/', $cleaned)) {
            return false;
        }

        // Additional country-specific validation if provided
        if ($countryCode !== null) {
            return $this->validatePhoneByCountry($cleaned, $countryCode);
        }

        return true;
    }

    /**
     * Validate phone by country code
     * 
     * @param string $phone Cleaned phone number
     * @param string $countryCode Country code (US, GB, TR, etc.)
     * @return bool
     */
    protected function validatePhoneByCountry(string $phone, string $countryCode): bool
    {
        $patterns = [
            'US' => '/^\+?1?[2-9]\d{2}[2-9](?!11)\d{6}$/',
            'GB' => '/^\+?44[1-9]\d{8,9}$/',
            'TR' => '/^\+?90[1-9]\d{9}$/',
            'DE' => '/^\+?49[1-9]\d{8,13}$/',
            'FR' => '/^\+?33[1-9]\d{8}$/',
        ];

        $pattern = $patterns[strtoupper($countryCode)] ?? null;

        if ($pattern === null) {
            return true; // No specific pattern, accept general format
        }

        return (bool) preg_match($pattern, $phone);
    }

    /**
     * Format phone number
     * 
     * @param string $phone Phone number
     * @param string $format Format (e.g., '(###) ###-####')
     * @return string
     */
    protected function formatPhone(string $phone, string $format = '(###) ###-####'): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        $formatted = $format;

        foreach (str_split($cleaned) as $digit) {
            $formatted = preg_replace('/#/', $digit, $formatted, 1);
        }

        return $formatted;
    }
}
