<?php

declare(strict_types=1);

namespace Conduit\Validation\SchemaType;

use DateTimeInterface;
use DateTimeImmutable;

/**
 * DateType
 * 
 * Date validation type.
 * Validates date values with format and range constraints.
 * 
 * @package Conduit\Validation\SchemaType
 */
class DateType extends BaseType
{
    protected string $typeName = 'date';
    
    protected string $format = 'Y-m-d';
    protected ?DateTimeInterface $minDate = null;
    protected ?DateTimeInterface $maxDate = null;

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

        // Try to parse the date
        $date = null;
        if ($value instanceof DateTimeInterface) {
            $date = $value;
        } elseif (is_string($value)) {
            $date = DateTimeImmutable::createFromFormat($this->format, $value);
            if ($date === false) {
                $errors[] = $this->getErrorMessage(
                    $field,
                    "The {$field} must be a valid date in format {$this->format}."
                );
                return $errors;
            }
        } else {
            $errors[] = $this->getErrorMessage($field, "The {$field} must be a date.");
            return $errors;
        }

        // Min date
        if ($this->minDate !== null && $date < $this->minDate) {
            $errors[] = $this->getErrorMessage(
                $field,
                "The {$field} must be after {$this->minDate->format($this->format)}."
            );
        }

        // Max date
        if ($this->maxDate !== null && $date > $this->maxDate) {
            $errors[] = $this->getErrorMessage(
                $field,
                "The {$field} must be before {$this->maxDate->format($this->format)}."
            );
        }

        return $errors;
    }

    /**
     * Set date format
     * 
     * @param string $format Date format (PHP date format)
     * @return self
     */
    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Set minimum date
     * 
     * @param DateTimeInterface|string $date Minimum date
     * @return self
     */
    public function after(DateTimeInterface|string $date): self
    {
        if (is_string($date)) {
            $date = new DateTimeImmutable($date);
        }
        $this->minDate = $date;
        return $this;
    }

    /**
     * Set maximum date
     * 
     * @param DateTimeInterface|string $date Maximum date
     * @return self
     */
    public function before(DateTimeInterface|string $date): self
    {
        if (is_string($date)) {
            $date = new DateTimeImmutable($date);
        }
        $this->maxDate = $date;
        return $this;
    }
}
