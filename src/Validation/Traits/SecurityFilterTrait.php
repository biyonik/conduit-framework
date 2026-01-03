<?php

declare(strict_types=1);

namespace Conduit\Validation\Traits;

/**
 * SecurityFilterTrait
 * 
 * Provides security filtering and sanitization functionality.
 * 
 * @package Conduit\Validation\Traits
 */
trait SecurityFilterTrait
{
    /**
     * Sanitize string (remove HTML tags and special characters)
     * 
     * @param string $value Value to sanitize
     * @return string
     */
    protected function sanitizeString(string $value): string
    {
        return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize email
     * 
     * @param string $email Email to sanitize
     * @return string
     */
    protected function sanitizeEmail(string $email): string
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize URL
     * 
     * @param string $url URL to sanitize
     * @return string
     */
    protected function sanitizeUrl(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }

    /**
     * Check for SQL injection patterns
     * 
     * @param string $value Value to check
     * @return bool True if potentially dangerous
     */
    protected function hasSqlInjectionPattern(string $value): bool
    {
        $patterns = [
            '/(\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b|\bCREATE\b)/i',
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(--|#|\/\*|\*\/)/i',
            '/(\bOR\b.*=.*\bOR\b)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for XSS patterns
     * 
     * @param string $value Value to check
     * @return bool True if potentially dangerous
     */
    protected function hasXssPattern(string $value): bool
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i', // Event handlers like onclick=
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Escape string for HTML output
     * 
     * @param string $value Value to escape
     * @return string
     */
    protected function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape string for JavaScript output
     * 
     * @param string $value Value to escape
     * @return string
     */
    protected function escapeJs(string $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
