<?php

declare(strict_types=1);

namespace Conduit\Http\Exceptions;

use Throwable;

/**
 * 403 Forbidden Exception
 * 
 * Authenticated kullanıcı ama yetkisi yok:
 * - User authenticated ama admin değil
 * - Post owner değil
 * - Permission yok (RBAC)
 * 
 * 401 vs 403:
 * - 401: Kim olduğunu bilmiyoruz (login yap)
 * - 403: Kim olduğunu biliyoruz ama yetkili değilsin
 * 
 * @package Conduit\Http\Exceptions
 */
class ForbiddenException extends HttpException
{
    /**
     * Constructor
     * 
     * @param string $message Hata mesajı (varsayılan: "Forbidden")
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
            statusCode: 403,
            message: $message ?: 'Forbidden',
            headers: $headers,
            code: $code,
            previous: $previous
        );
    }
}