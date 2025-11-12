<?php 

declare(strict_types=1);

namespace Conduit\Routing\Exceptions;

use Exception;

/**
 * Route Not Found Exception
 * 
 * Named route bulunamadığında throw edilir.
 * 
 * @package Conduit\Routing\Exceptions
 */
class RouteNotFoundException extends Exception
{
    /**
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Route not found', int $code = 404, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}