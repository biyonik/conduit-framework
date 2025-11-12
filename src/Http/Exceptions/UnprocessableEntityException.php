<?php

declare(strict_types=1);

namespace Conduit\Http\Exceptions;

use Throwable;

/**
 * 422 Unprocessable Entity Exception
 * 
 * Request syntax doğru ama semantik hatalı:
 * - Validation error (email formatı yanlış)
 * - Business rule violation (stok yok)
 * - Duplicate entry (email zaten kayıtlı)
 * 
 * 400 vs 422:
 * - 400: Request syntax'ı bozuk (malformed JSON)
 * - 422: Request syntax'ı doğru ama data hatalı (validation fail)
 * 
 * Modern API'lerde validation error için tercih edilen status code.
 * 
 * @package Conduit\Http\Exceptions
 */
class UnprocessableEntityException extends HttpException
{
    /**
     * Validation hataları
     */
    protected array $errors = [];

    /**
     * Constructor
     * 
     * @param string $message Hata mesajı (varsayılan: "Unprocessable Entity")
     * @param array $errors Validation error'ları (field => [messages])
     * @param Throwable|null $previous Önceki exception
     * @param int $code Exception code
     * @param array $headers Ek headers
     */
    public function __construct(
        string $message = '',
        array $errors = [],
        ?Throwable $previous = null,
        int $code = 0,
        array $headers = []
    ) {
        $this->errors = $errors;

        parent::__construct(
            statusCode: 422,
            message: $message ?: 'Unprocessable Entity',
            headers: $headers,
            code: $code,
            previous: $previous
        );
    }

    /**
     * Validation error'larını al
     * 
     * @return array Field-based error array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Validation error'ı var mı?
     * 
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Belirli bir field için error var mı?
     * 
     * @param string $field Field adı
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Belirli bir field'in error'larını al
     * 
     * @param string $field Field adı
     * @return array Error mesajları
     */
    public function getError(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Exception'ı array formatına çevir
     * Validation error'ları dahil
     * 
     * @param bool $debug Debug mode
     * @return array
     */
    public function toArray(bool $debug = false): array
    {
        $data = parent::toArray($debug);

        // Validation error'ları ekle
        if ($this->hasErrors()) {
            $data['errors'] = $this->getErrors();
        }

        return $data;
    }
}