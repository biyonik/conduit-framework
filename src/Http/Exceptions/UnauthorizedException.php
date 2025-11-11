<?php

declare(strict_types=1);

namespace Conduit\Http\Exceptions;

use Throwable;

/**
 * 401 Unauthorized Exception
 * 
 * Kimlik doğrulama gerekli ama yapılmamış veya başarısız:
 * - Token yok
 * - Token geçersiz/expired
 * - Session yok/expired
 * - Credentials yanlış
 * 
 * WWW-Authenticate header ile authentication scheme belirtilir.
 * 
 * @package Conduit\Http\Exceptions
 */
class UnauthorizedException extends HttpException
{
    /**
     * Constructor
     * 
     * @param string $challenge WWW-Authenticate challenge (örn: "Bearer realm=\"API\"")
     * @param string $message Hata mesajı
     * @param Throwable|null $previous Önceki exception
     * @param int $code Exception code
     */
    public function __construct(
        string $challenge = '',
        string $message = '',
        ?Throwable $previous = null,
        int $code = 0
    ) {
        $headers = [];

        // WWW-Authenticate header ekle (RFC 7235)
        if (!empty($challenge)) {
            $headers['WWW-Authenticate'] = $challenge;
        }

        parent::__construct(
            statusCode: 401,
            message: $message ?: 'Unauthorized',
            headers: $headers,
            code: $code,
            previous: $previous
        );
    }
}