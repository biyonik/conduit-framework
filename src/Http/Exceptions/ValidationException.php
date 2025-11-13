<?php

declare(strict_types=1);

namespace Conduit\Http\Exceptions;

/**
 * 422 Unprocessable Entity (Validation Error)
 */
class ValidationException extends HttpException
{
    protected array $errors = [];

    public function __construct(
        array $errors,
        string $message = 'Validation failed',
        array $headers = [],
        \Throwable $previous = null
    ) {
        $this->errors = $errors;
        parent::__construct(
            statusCode: 422,message: $message,headers: $headers, previous: $previous
        );
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
