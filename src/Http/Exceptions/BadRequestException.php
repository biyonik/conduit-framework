<?php

declare(strict_types=1);

namespace Conduit\Http\Exceptions;

use Throwable;

/**
 * 400 Bad Request Exception
 * 
 * Geçersiz request (malformed, invalid syntax):
 * - Geçersiz JSON
 * - Eksik required parameter
 * - Invalid format
 * 
 * @package Conduit\Http\Exceptions
 */
class BadRequestException extends HttpException
{
    /**
     * Constructor
     * 
     * @param string $message Hata mesajı (varsayılan: "Bad Request")
     * @param Throwable|null $previous Önceki exception
     * @param int $code Exception code
     * @param array $headers Ek headers
     */
    public function __construct(
        string $message = '',
        ?Throwable $previous = null,
        int $code = 0,
        array $headers = []
    ) {
        parent::__construct(
            statusCode: 400,
            message: $message ?: 'Bad Request',
            headers: $headers,
            code: $code,
            previous: $previous
        );
    }
}