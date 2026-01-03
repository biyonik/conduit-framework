<?php

declare(strict_types=1);

namespace Conduit\Validation\Traits;

/**
 * PaymentValidationTrait
 * 
 * Provides credit card and IBAN validation functionality.
 * 
 * @package Conduit\Validation\Traits
 */
trait PaymentValidationTrait
{
    /**
     * Validate credit card using Luhn algorithm
     * 
     * @param string $number Card number
     * @return bool
     */
    protected function isValidCreditCard(string $number): bool
    {
        // Remove spaces and hyphens
        $number = preg_replace('/[\s-]/', '', $number);

        // Check if it's numeric and has valid length
        if (!ctype_digit($number) || strlen($number) < 13 || strlen($number) > 19) {
            return false;
        }

        // Luhn algorithm
        $sum = 0;
        $length = strlen($number);
        $parity = $length % 2;

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $number[$i];
            if ($i % 2 === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return ($sum % 10) === 0;
    }

    /**
     * Get credit card type
     * 
     * @param string $number Card number
     * @return string|null
     */
    protected function getCreditCardType(string $number): ?string
    {
        $number = preg_replace('/[\s-]/', '', $number);

        $patterns = [
            'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
            'mastercard' => '/^5[1-5][0-9]{14}$/',
            'amex' => '/^3[47][0-9]{13}$/',
            'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
            'diners' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
            'jcb' => '/^(?:2131|1800|35\d{3})\d{11}$/',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $number)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Validate IBAN
     * 
     * @param string $iban IBAN string
     * @return bool
     */
    protected function isValidIban(string $iban): bool
    {
        // Remove spaces
        $iban = strtoupper(preg_replace('/\s/', '', $iban));

        // Check length (15-34 characters)
        if (strlen($iban) < 15 || strlen($iban) > 34) {
            return false;
        }

        // Move first 4 characters to end
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        // Replace letters with numbers (A=10, B=11, etc.)
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numeric .= (string) (ord($char) - ord('A') + 10);
            } else {
                $numeric .= $char;
            }
        }

        // Validate using modulo 97
        return $this->mod97($numeric) === 1;
    }

    /**
     * Calculate modulo 97 for large numbers
     * 
     * @param string $number Large number as string
     * @return int
     */
    protected function mod97(string $number): int
    {
        $remainder = 0;
        for ($i = 0; $i < strlen($number); $i++) {
            $remainder = ($remainder * 10 + (int) $number[$i]) % 97;
        }
        return $remainder;
    }
}
