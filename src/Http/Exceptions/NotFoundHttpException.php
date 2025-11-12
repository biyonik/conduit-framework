<?php

declare(strict_types=1);

namespace Conduit\Http\Exceptions;

use Throwable;

/**
 * 404 Not Found Exception
 * 
 * İstenen kaynak bulunamadığında fırlatılır:
 * - Route bulunamadı
 * - Resource bulunamadı (User, Post, etc.)
 * - File bulunamadı
 * 
 * @package Conduit\Http\Exceptions
 */
class NotFoundHttpException extends HttpException
{
    /**
     * Constructor
     * 
     * @param string $message Hata mesajı (varsayılan: "Not Found")
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
            statusCode: 404,
            message: $message ?: 'Not Found',
            headers: $headers,
            code: $code,
            previous: $previous
        );
    }
}