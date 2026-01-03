<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

use Conduit\Validation\Traits\PaymentValidationTrait;

/**
 * IbanType
 * 
 * IBAN validation type.
 * Validates International Bank Account Numbers.
 * 
 * @package Conduit\Validation\SchemaType
 */
class IbanType extends BaseType
{
    use PaymentValidationTrait;

    protected string $typeName = 'iban';
    
    protected ?string $countryCode = null;

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

        // Validate IBAN
        if (!$this->isValidIban($value)) {
            $errors[] = $this->getErrorMessage($field, "The {$field} must be a valid IBAN.");
        }

        // Validate country code if specified
        if ($this->countryCode !== null) {
            $detectedCountry = substr($value, 0, 2);
            if (strtoupper($detectedCountry) !== strtoupper($this->countryCode)) {
                $errors[] = $this->getErrorMessage(
                    $field,
                    "The {$field} must be a valid IBAN for country {$this->countryCode}."
                );
            }
        }

        return $errors;
    }

    /**
     * Set required country code
     * 
     * @param string $code Country code (2 letters)
     * @return self
     */
    public function country(string $code): self
    {
        $this->countryCode = strtoupper($code);
        return $this;
    }
}
