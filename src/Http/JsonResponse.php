<?php

declare(strict_types=1);

namespace Conduit\Http;

use Conduit\Http\Message\Stream;
use InvalidArgumentException;
use JsonException;

/**
 * JSON Response
 * 
 * API-first framework için JSON response helper.
 * Standart success/error formatlarını otomatik oluşturur.
 * 
 * @package Conduit\Http
 */
class JsonResponse extends Response
{
    /**
     * JSON encode options
     */
    private int $encodingOptions;

    /**
     * Constructor
     * 
     * @param mixed $data Encode edilecek data
     * @param int $statusCode HTTP status code
     * @param array $headers Ek headers
     * @param int $encodingOptions JSON encode options
     * @throws InvalidArgumentException
     */
    public function __construct(
        mixed $data = null,
        int $statusCode = 200,
        array $headers = [],
        int $encodingOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
    ) {
        $this->encodingOptions = $encodingOptions;

        // JSON body oluştur
        $json = $this->encodeJson($data);
        $body = Stream::create($json);

        // Content-Type ekle
        $headers['Content-Type'] = 'application/json; charset=UTF-8';

        parent::__construct(
            statusCode: $statusCode,
            headers: $headers,
            body: $body
        );
    }

    /**
     * Success response oluştur (Standart API format)
     * 
     * Format:
     * {
     *   "success": true,
     *   "data": { ... },
     *   "meta": { "timestamp": 1234567890 }
     * }
     * 
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     * @param array $meta Ek metadata
     * @param array $headers Ek headers
     * @return self
     */
    public static function success(
        mixed $data = null,
        int $statusCode = 200,
        array $meta = [],
        array $headers = []
    ): self {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        // Metadata ekle
        if (!empty($meta)) {
            $response['meta'] = array_merge([
                'timestamp' => time(),
            ], $meta);
        }

        return new self($response, $statusCode, $headers);
    }

    /**
     * Error response oluştur (Standart API format)
     * 
     * Format:
     * {
     *   "success": false,
     *   "error": {
     *     "message": "Error message",
     *     "code": "ERROR_CODE",
     *     "details": { ... }
     *   }
     * }
     * 
     * @param string $message Hata mesajı
     * @param int $statusCode HTTP status code
     * @param string|null $code Error code (opsiyonel)
     * @param mixed $details Ek detaylar (validation errors, etc.)
     * @param array $headers Ek headers
     * @return self
     */
    public static function error(
        string $message,
        int $statusCode = 400,
        ?string $code = null,
        mixed $details = null,
        array $headers = []
    ): self {
        $error = [
            'message' => $message,
        ];

        if ($code !== null) {
            $error['code'] = $code;
        }

        if ($details !== null) {
            $error['details'] = $details;
        }

        $response = [
            'success' => false,
            'error' => $error,
        ];

        return new self($response, $statusCode, $headers);
    }

    /**
     * Created response (201)
     * 
     * @param mixed $data Created resource data
     * @param string|null $location Resource URL (Location header)
     * @param array $headers Ek headers
     * @return self
     */
    public static function created(
        mixed $data = null,
        ?string $location = null,
        array $headers = []
    ): self {
        if ($location !== null) {
            $headers['Location'] = $location;
        }

        return self::success($data, 201, [], $headers);
    }

    /**
     * No Content response (204)
     * 
     * @param array $headers Ek headers
     * @return self
     */
    public static function noContent(array $headers = []): self
    {
        return new self(null, 204, $headers);
    }

    /**
     * Accepted response (202)
     * 
     * Async işlemler için (queue'ya atıldı, background'da çalışacak)
     * 
     * @param mixed $data Response data
     * @param array $headers Ek headers
     * @return self
     */
    public static function accepted(mixed $data = null, array $headers = []): self
    {
        return self::success($data, 202, [], $headers);
    }

    /**
     * Paginated response
     * 
     * Format:
     * {
     *   "success": true,
     *   "data": [ ... ],
     *   "pagination": {
     *     "total": 150,
     *     "per_page": 20,
     *     "current_page": 2,
     *     "last_page": 8,
     *     "from": 21,
     *     "to": 40
     *   }
     * }
     * 
     * @param array $data Items (current page)
     * @param int $total Total items
     * @param int $perPage Items per page
     * @param int $currentPage Current page number
     * @param array $headers Ek headers
     * @return self
     */
    public static function paginated(
        array $data,
        int $total,
        int $perPage,
        int $currentPage,
        array $headers = []
    ): self {
        $lastPage = (int) ceil($total / $perPage);
        $from = ($currentPage - 1) * $perPage + 1;
        $to = min($currentPage * $perPage, $total);

        $response = [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
        ];

        return new self($response, 200, $headers);
    }

    /**
     * Validation error response (422)
     * 
     * @param array $errors Validation errors [field => [messages]]
     * @param string $message Ana hata mesajı
     * @param array $headers Ek headers
     * @return self
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed',
        array $headers = []
    ): self {
        return self::error(
            message: $message,
            statusCode: 422,
            code: 'VALIDATION_ERROR',
            details: $errors,
            headers: $headers
        );
    }

    /**
     * Unauthorized response (401)
     * 
     * @param string $message Hata mesajı
     * @param array $headers Ek headers
     * @return self
     */
    public static function unauthorized(
        string $message = 'Unauthorized',
        array $headers = []
    ): self {
        return self::error($message, 401, 'UNAUTHORIZED', null, $headers);
    }

    /**
     * Forbidden response (403)
     * 
     * @param string $message Hata mesajı
     * @param array $headers Ek headers
     * @return self
     */
    public static function forbidden(
        string $message = 'Forbidden',
        array $headers = []
    ): self {
        return self::error($message, 403, 'FORBIDDEN', null, $headers);
    }

    /**
     * Not Found response (404)
     * 
     * @param string $message Hata mesajı
     * @param array $headers Ek headers
     * @return self
     */
    public static function notFound(
        string $message = 'Not Found',
        array $headers = []
    ): self {
        return self::error($message, 404, 'NOT_FOUND', null, $headers);
    }

    /**
     * Too Many Requests response (429)
     * 
     * @param int $retryAfter Saniye cinsinden bekleme süresi
     * @param string $message Hata mesajı
     * @param array $headers Ek headers
     * @return self
     */
    public static function tooManyRequests(
        int $retryAfter = 60,
        string $message = 'Too Many Requests',
        array $headers = []
    ): self {
        $headers['Retry-After'] = (string) $retryAfter;
        
        return self::error($message, 429, 'RATE_LIMIT_EXCEEDED', [
            'retry_after' => $retryAfter,
        ], $headers);
    }

    /**
     * Internal Server Error response (500)
     * 
     * @param string $message Hata mesajı
     * @param array $headers Ek headers
     * @return self
     */
    public static function serverError(
        string $message = 'Internal Server Error',
        array $headers = []
    ): self {
        return self::error($message, 500, 'SERVER_ERROR', null, $headers);
    }

    /**
     * Data'yı JSON'a encode et
     * 
     * @param mixed $data
     * @return string JSON string
     * @throws InvalidArgumentException
     */
    private function encodeJson(mixed $data): string
    {
        if ($data === null) {
            return '';
        }

        try {
            $json = json_encode($data, $this->encodingOptions | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Unable to encode data to JSON: ' . $e->getMessage());
        }

        return $json;
    }

    /**
     * JSON encode options'ı değiştir
     * 
     * @param int $options JSON encode options
     * @return self
     */
    public function withEncodingOptions(int $options): self
    {
        $new = clone $this;
        $new->encodingOptions = $options;
        
        // Body'yi yeniden encode et
        $data = json_decode((string) $this->getBody(), true);
        $json = $new->encodeJson($data);
        $new->setBody(Stream::create($json));

        return $new;
    }

    /**
     * Pretty print JSON (development için)
     * 
     * @return self
     */
    public function withPrettyPrint(): self
    {
        return $this->withEncodingOptions($this->encodingOptions | JSON_PRETTY_PRINT);
    }
}