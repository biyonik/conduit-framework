<?php

declare(strict_types=1);

namespace Conduit\Core\Contracts;

/**
 * Bootable Interface
 * 
 * Boot edilebilir servisler için sözleşme.
 * Service provider'lar bu interface'i implement eder.
 * 
 * @package Conduit\Core\Contracts
 */
interface BootableInterface
{
    /**
     * Servisi başlat
     * 
     * Tüm provider'lar register edildikten SONRA çalışır.
     * Bu aşamada container'dan servis çekilebilir.
     * 
     * @return void
     */
    public function boot(): void;
}