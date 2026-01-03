<?php

declare(strict_types=1);

namespace Conduit\Validation;

use Conduit\Validation\Contracts\ValidationSchemaInterface;
use Conduit\Validation\Contracts\ValidationTypeInterface;
use Conduit\Validation\Contracts\ValidationResultInterface;

/**
 * ValidationSchema
 * 
 * Builds and executes validation schemas.
 * Fluent API for defining field validations.
 * 
 * @package Conduit\Validation
 */
class ValidationSchema implements ValidationSchemaInterface
{
    /**
     * Registered field validations
     * 
     * @var array<string, ValidationTypeInterface>
     */
    protected array $fields = [];

    /**
     * Create a new validation schema
     * 
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * {@inheritDoc}
     */
    public function field(string $field, ValidationTypeInterface $type): self
    {
        $this->fields[$field] = $type;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $data): ValidationResultInterface
    {
        $result = new ValidationResult();

        foreach ($this->fields as $fieldName => $validator) {
            $value = $data[$fieldName] ?? null;

            // Check if field is missing and required
            if (!array_key_exists($fieldName, $data)) {
                if ($validator->isRequired()) {
                    $result->addError($fieldName, "The {$fieldName} field is required.");
                }
                continue;
            }

            // Validate the field
            $errors = $validator->validate($value, $fieldName);

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $result->addError($fieldName, $error);
                }
            } else {
                // Add to validated data if no errors
                $result->addValidatedData($fieldName, $value);
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * {@inheritDoc}
     */
    public function hasField(string $field): bool
    {
        return isset($this->fields[$field]);
    }

    /**
     * Validate data and throw exception on failure
     * 
     * @param array<string, mixed> $data Data to validate
     * @return array<string, mixed> Validated data
     * @throws Exceptions\ValidationException
     */
    public function validateOrFail(array $data): array
    {
        $result = $this->validate($data);

        if ($result->fails()) {
            throw new Exceptions\ValidationException(
                'Validation failed',
                $result->getErrors()
            );
        }

        return $result->getValidatedData();
    }
}
