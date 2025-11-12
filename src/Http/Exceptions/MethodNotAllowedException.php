<?php

declare(strict_types=1);

namespace Conduit\Http\Exceptions;

use Throwable;

/**
 * 405 Method Not Allowed Exception
 * 
 * Route bulundu ama HTTP method eşleşmedi:
 * - Route: GET /api/users/{id} tanımlı
 * - Request: POST /api/users/123 geldi
 * 
 * Allow header'ı ile izin verilen methodlar bildirilir.
 * 
 * @package Conduit\Http\Exceptions
 */
class MethodNotAllowedException extends HttpException
{
    /**
     * Constructor
     * 
     * @param array $allowedMethods İzin verilen HTTP methodları
     * @param string $message Hata mesajı
     * @param Throwable|null $previous Önceki exception
     * @param int $code Exception code
     */
    public function __construct(
        array $allowedMethods = [],
        string $message = '',
        ?Throwable $previous = null,
        int $code = 0
    ) {
        $headers = [];

        // Allow header ekle (RFC 7231)
        if (!empty($allowedMethods)) {
            $headers['Allow'] = implode(', ', $allowedMethods);
        }

        // Default mesaj
        if (empty($message)) {
            $message = 'Method Not Allowed';
            if (!empty($allowedMethods)) {
                $message .= '. Allowed methods: ' . implode(', ', $allowedMethods);
            }
        }

        parent::__construct(
            statusCode: 405,
            message: $message,
            headers: $headers,
            code: $code,
            previous: $previous
        );
    }
}