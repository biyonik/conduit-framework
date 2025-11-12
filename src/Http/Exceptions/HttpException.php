<?php

declare(strict_types=1);

namespace Conduit\Http\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base HTTP Exception
 * 
 * Tüm HTTP exception'ların base sınıfı.
 * HTTP status code ve headers taşır.
 * 
 * @package Conduit\Http\Exceptions
 */
class HttpException extends RuntimeException
{
    /**
     * HTTP status code
     */
    protected int $statusCode;

    /**
     * Ek HTTP headers
     */
    protected array $headers;

    /**
     * Constructor
     * 
     * @param int $statusCode HTTP status code
     * @param string $message Hata mesajı
     * @param array $headers Ek headers
     * @param int $code Exception code (varsayılan 0)
     * @param Throwable|null $previous Önceki exception (chaining için)
     */
    public function __construct(
        int $statusCode,
        string $message = '',
        array $headers = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        // Eğer mesaj boşsa, status code'dan default mesaj üret
        if (empty($message)) {
            $message = $this->getDefaultMessageForStatusCode($statusCode);
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * HTTP status code'u al
     * 
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * HTTP headers'ları al
     * 
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Header ekle
     * 
     * @param string $name Header adı
     * @param string $value Header değeri
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Status code için default mesaj üret
     * 
     * @param int $statusCode HTTP status code
     * @return string Default mesaj
     */
    protected function getDefaultMessageForStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            // 1xx Informational
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',

            // 2xx Success
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',

            // 3xx Redirection
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',

            // 4xx Client Error
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => "I'm a teapot", // RFC 2324 (April Fools' joke)
            419 => 'Session Has Expired', // Laravel custom
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',

            // 5xx Server Error
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',

            // Default
            default => 'Unknown Error',
        };
    }

    /**
     * Exception'ı array formatına çevir
     * 
     * JSON response için kullanılır.
     * 
     * @param bool $debug Debug mode (stack trace dahil et)
     * @return array
     */
    public function toArray(bool $debug = false): array
    {
        $data = [
            'message' => $this->getMessage(),
            'status_code' => $this->getStatusCode(),
        ];

        if ($debug) {
            $data['exception'] = get_class($this);
            $data['file'] = $this->getFile();
            $data['line'] = $this->getLine();
            $data['trace'] = $this->getTrace();
        }

        return $data;
    }
}