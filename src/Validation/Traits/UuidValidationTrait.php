<?php

declare(strict_types=1);

namespace Conduit\Validation\Traits;

/**
 * UuidValidationTrait
 * 
 * Provides UUID validation functionality.
 * 
 * @package Conduit\Validation\Traits
 */
trait UuidValidationTrait
{
    /**
     * Validate UUID
     * 
     * @param string $uuid UUID string
     * @param int $version UUID version (1-5, or 0 for any)
     * @return bool
     */
    protected function isValidUuid(string $uuid, int $version = 4): bool
    {
        $pattern = match ($version) {
            1 => '/^[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            2 => '/^[0-9a-f]{8}-[0-9a-f]{4}-2[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            3 => '/^[0-9a-f]{8}-[0-9a-f]{4}-3[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            4 => '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            5 => '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            default => '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        };

        return (bool) preg_match($pattern, $uuid);
    }

    /**
     * Generate UUID v4
     * 
     * @return string
     */
    protected function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
