<?php

declare(strict_types=1);

namespace Conduit\Core\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * Container Exception
 * 
 * Container işlemlerinde genel hatalar için.
 * PSR-11 ContainerExceptionInterface implement eder.
 * 
 * @package Conduit\Core\Exceptions
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
    /**
     * Container exception oluştur
     * 
     * @param string $message Hata mesajı
     * @param int $code Hata kodu
     * @param \Throwable|null $previous Önceki exception (chaining)
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Binding bulunamadı hatası
     * 
     * @param string $abstract Aranan servis adı
     * @return self
     */
    public static function bindingNotFound(string $abstract): self
    {
        return new self("No binding found for [{$abstract}] in container.");
    }

    /**
     * Circular dependency tespit edildi
     * 
     * @param string $abstract Döngüsel bağımlılık olan servis
     * @return self
     */
    public static function circularDependency(string $abstract): self
    {
        return new self("Circular dependency detected for [{$abstract}].");
    }
}