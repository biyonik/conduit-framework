<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

use Conduit\Validation\Traits\AdvancedStringValidationTrait;
use Conduit\Validation\Traits\PhoneValidationTrait;
use Conduit\Validation\Traits\IpValidationTrait;

/**
 * AdvancedStringType
 * 
 * Advanced string validation type.
 * Provides additional validation for special string formats.
 * 
 * @package Conduit\Validation\SchemaType
 */
class AdvancedStringType extends StringType
{
    use AdvancedStringValidationTrait;
    use PhoneValidationTrait;
    use IpValidationTrait;

    protected string $typeName = 'advancedString';
    
    protected ?string $formatType = null;

    /**
     * {@inheritDoc}
     */
    public function validate(mixed $value, string $field): array
    {
        // First run basic string validation
        $errors = parent::validate($value, $field);
        
        if (!empty($errors)) {
            return $errors;
        }

        // Skip if optional and empty
        if (!$this->isRequired && $this->isEmpty($value)) {
            return $errors;
        }

        // Validate format type if specified
        if ($this->formatType !== null && is_string($value)) {
            $isValid = match ($this->formatType) {
                'phone' => $this->isValidPhone($value),
                'ipv4' => $this->isValidIpv4($value),
                'ipv6' => $this->isValidIpv6($value),
                'ip' => $this->isValidIp($value),
                'slug' => $this->isValidSlug($value),
                'username' => $this->isValidUsername($value),
                'alphanumeric' => $this->isAlphanumeric($value),
                default => true,
            };

            if (!$isValid) {
                $errors[] = $this->getErrorMessage(
                    $field,
                    "The {$field} must be a valid {$this->formatType}."
                );
            }
        }

        return $errors;
    }

    /**
     * Validate as phone number
     * 
     * @return self
     */
    public function phone(): self
    {
        $this->formatType = 'phone';
        return $this;
    }

    /**
     * Validate as IPv4 address
     * 
     * @return self
     */
    public function ipv4(): self
    {
        $this->formatType = 'ipv4';
        return $this;
    }

    /**
     * Validate as IPv6 address
     * 
     * @return self
     */
    public function ipv6(): self
    {
        $this->formatType = 'ipv6';
        return $this;
    }

    /**
     * Validate as IP address (v4 or v6)
     * 
     * @return self
     */
    public function ip(): self
    {
        $this->formatType = 'ip';
        return $this;
    }

    /**
     * Validate as slug
     * 
     * @return self
     */
    public function slug(): self
    {
        $this->formatType = 'slug';
        return $this;
    }

    /**
     * Validate as username
     * 
     * @return self
     */
    public function username(): self
    {
        $this->formatType = 'username';
        return $this;
    }

    /**
     * Validate as alphanumeric
     * 
     * @return self
     */
    public function alphanumeric(): self
    {
        $this->formatType = 'alphanumeric';
        return $this;
    }
}
