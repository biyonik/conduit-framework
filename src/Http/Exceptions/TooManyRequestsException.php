<?php

declare(strict_types=1);

namespace Conduit\Http\Exceptions;

use Throwable;

/**
 * 429 Too Many Requests Exception
 * 
 * Rate limit aşıldı:
 * - API rate limit
 * - Login attempts limit
 * - Brute-force protection
 * 
 * Retry-After header ile ne zaman tekrar deneyebileceği bildirilir.
 * 
 * @package Conduit\Http\Exceptions
 */
class TooManyRequestsException extends HttpException
{
    /**
     * Constructor
     * 
     * @param int $retryAfter Saniye cinsinden bekleme süresi
     * @param string $message Hata mesajı
     * @param Throwable|null $previous Önceki exception
     * @param int $code Exception code
     */
    public function __construct(
        int $retryAfter = 60,
        string $message = '',
        ?Throwable $previous = null,
        int $code = 0
    ) {
        $headers = [
            'Retry-After' => (string) $retryAfter,
        ];

        // Default mesaj
        if (empty($message)) {
            $message = "Too Many Requests. Retry after {$retryAfter} seconds.";
        }

        parent::__construct(
            statusCode: 429,
            message: $message,
            headers: $headers,
            code: $code,
            previous: $previous
        );
    }

    /**
     * Retry-After değerini al
     * 
     * @return int Saniye cinsinden
     */
    public function getRetryAfter(): int
    {
        return (int) ($this->getHeaders()['Retry-After'] ?? 0);
    }
}