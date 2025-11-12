<?php

declare(strict_types=1);

namespace Conduit\Middleware;

use Closure;
use Conduit\Http\Contracts\RequestInterface;
use Conduit\Http\Contracts\ResponseInterface;

/**
 * Middleware Interface
 * 
 * Tüm middleware'lerin implement etmesi gereken temel contract.
 * Onion pattern implementation için Closure-based next() yaklaşımı kullanır.
 * 
 * @package Conduit\Middleware
 */
interface MiddlewareInterface
{
    /**
     * Middleware'i handle et
     * 
     * Request'i işle, sonraki middleware/controller'a geç,
     * dönen response'u işle ve return et.
     * 
     * Onion Pattern:
     * Request → MW1 → MW2 → MW3 → Controller
     *                                  ↓
     * Response ← MW1 ← MW2 ← MW3 ← Return
     * 
     * @param RequestInterface $request Request nesnesi
     * @param Closure $next Sonraki middleware/controller
     * @return ResponseInterface Response nesnesi
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface;

    /**
     * Middleware parametrelerini set et (optional)
     * 
     * Bu method opsiyoneldir. Parameterized middleware'ler 
     * (throttle:60,1 gibi) için kullanılır.
     * 
     * @param array<string> $parameters Parameter array
     * @return self
     */
    public function setParameters(array $parameters): self;
}