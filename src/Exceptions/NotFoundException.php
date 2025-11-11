<?php

declare(strict_types=1);

namespace Conduit\Core\Exceptions;

use Exception;

/**
 * Not Found Exception
 * 
 * Genel "bulunamadı" hataları için.
 * 
 * @package Conduit\Core\Exceptions
 */
class NotFoundException extends Exception
{
    /**
     * Dosya bulunamadı
     * 
     * @param string $file Dosya yolu
     * @return self
     */
    public static function file(string $file): self
    {
        return new self("File not found: {$file}");
    }

    /**
     * Config dosyası bulunamadı
     * 
     * @param string $config Config adı
     * @return self
     */
    public static function config(string $config): self
    {
        return new self("Configuration file not found: {$config}");
    }

    /**
     * Class bulunamadı
     * 
     * @param string $class Class adı
     * @return self
     */
    public static function class(string $class): self
    {
        return new self("Class not found: {$class}");
    }
}