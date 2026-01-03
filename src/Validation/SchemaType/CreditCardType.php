<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

use Conduit\Validation\Traits\PaymentValidationTrait;

/**
 * CreditCardType
 * 
 * Credit card validation type.
 * Validates credit card numbers using Luhn algorithm.
 * 
 * @package Conduit\Validation\SchemaType
 */
class CreditCardType extends BaseType
{
    use PaymentValidationTrait;

    protected string $typeName = 'creditCard';
    
    protected ?string $cardType = null;

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

        // Validate credit card
        if (!$this->isValidCreditCard($value)) {
            $errors[] = $this->getErrorMessage($field, "The {$field} must be a valid credit card number.");
        }

        // Validate specific card type if specified
        if ($this->cardType !== null) {
            $detectedType = $this->getCreditCardType($value);
            if ($detectedType !== $this->cardType) {
                $errors[] = $this->getErrorMessage(
                    $field,
                    "The {$field} must be a valid {$this->cardType} card."
                );
            }
        }

        return $errors;
    }

    /**
     * Set required card type
     * 
     * @param string $type Card type (visa, mastercard, amex, etc.)
     * @return self
     */
    public function type(string $type): self
    {
        $this->cardType = $type;
        return $this;
    }
}
