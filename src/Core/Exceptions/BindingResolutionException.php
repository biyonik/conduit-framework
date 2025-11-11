<?php

declare(strict_types=1);

namespace Conduit\Core\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Binding Resolution Exception
 * 
 * Container'dan servis resolve edilirken oluşan hatalar.
 * PSR-11 NotFoundExceptionInterface implement eder.
 * 
 * @package Conduit\Core\Exceptions
 */
class BindingResolutionException extends ContainerException implements NotFoundExceptionInterface
{
    /**
     * Class instantiate edilemedi
     * 
     * @param string $class Instantiate edilemeyen class
     * @param string $reason Sebep
     * @return self
     */
    public static function cannotInstantiate(string $class, string $reason): self
    {
        return new self("Cannot instantiate [{$class}]: {$reason}");
    }

    /**
     * Constructor parametresi çözümlenemedi
     * 
     * @param string $class Class adı
     * @param string $parameter Parametre adı
     * @return self
     */
    public static function unresolvedParameter(string $class, string $parameter): self
    {
        return new self("Unresolved dependency for parameter [{$parameter}] in class [{$class}].");
    }

    /**
     * Interface'e somut implementation bind edilmemiş
     * 
     * @param string $interface Interface adı
     * @return self
     */
    public static function unboundInterface(string $interface): self
    {
        return new self("Interface [{$interface}] is not bound to any concrete implementation.");
    }
}