<?php

declare(strict_types=1);

namespace Conduit\Http;

use Conduit\Http\Contracts\RequestInterface;
use Conduit\Validation\ValidationSchema;
use Conduit\Validation\Contracts\ValidationSchemaInterface;
use Conduit\Validation\Exceptions\ValidationException;

/**
 * FormRequest
 * 
 * Base class for form request validation.
 * Extends Request with automatic validation capabilities.
 * 
 * Usage:
 * ```php
 * class CreateUserRequest extends FormRequest
 * {
 *     protected function rules(): ValidationSchemaInterface
 *     {
 *         return ValidationSchema::create()
 *             ->field('name', (new StringType())->required()->min(3))
 *             ->field('email', (new StringType())->required()->email())
 *             ->field('age', (new NumberType())->required()->min(18));
 *     }
 * }
 * ```
 * 
 * @package Conduit\Http
 */
abstract class FormRequest
{
    /**
     * The underlying request instance
     */
    protected RequestInterface $request;

    /**
     * Validated data
     * 
     * @var array<string, mixed>
     */
    protected array $validatedData = [];

    /**
     * Constructor
     * 
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Create form request from request instance
     * 
     * @param RequestInterface $request
     * @return static
     * @throws ValidationException
     */
    public static function createFrom(RequestInterface $request): static
    {
        $instance = new static($request);
        $instance->validate();
        return $instance;
    }

    /**
     * Define validation rules
     * 
     * @return ValidationSchemaInterface
     */
    abstract protected function rules(): ValidationSchemaInterface;

    /**
     * Authorize the request (override in subclasses)
     * 
     * @return bool
     */
    protected function authorize(): bool
    {
        return true;
    }

    /**
     * Get data to be validated
     * 
     * @return array<string, mixed>
     */
    protected function validationData(): array
    {
        return $this->request->all();
    }

    /**
     * Validate the request
     * 
     * @return void
     * @throws ValidationException
     * @throws \Conduit\Http\Exceptions\ForbiddenException
     */
    protected function validate(): void
    {
        // Check authorization
        if (!$this->authorize()) {
            throw new \Conduit\Http\Exceptions\ForbiddenException(
                'This action is unauthorized.'
            );
        }

        // Get validation rules
        $schema = $this->rules();

        // Validate data
        $data = $this->validationData();
        $result = $schema->validate($data);

        // Check for validation errors
        if ($result->fails()) {
            throw new ValidationException(
                'The given data was invalid.',
                $result->getErrors(),
                422
            );
        }

        // Store validated data
        $this->validatedData = $result->getValidatedData();
    }

    /**
     * Get validated data
     * 
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Get a validated input value
     * 
     * @param string $key Input key
     * @param mixed $default Default value
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->validatedData[$key] ?? $default;
    }

    /**
     * Check if validated data has a key
     * 
     * @param string $key Input key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->validatedData);
    }

    /**
     * Get only specific validated fields
     * 
     * @param array<string> $keys Field keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key(
            $this->validatedData,
            array_flip($keys)
        );
    }

    /**
     * Get all validated fields except specific ones
     * 
     * @param array<string> $keys Field keys to exclude
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key(
            $this->validatedData,
            array_flip($keys)
        );
    }

    /**
     * Get the underlying request
     * 
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Forward calls to the underlying request
     * 
     * @param string $method Method name
     * @param array<mixed> $parameters Parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->request->$method(...$parameters);
    }
}
