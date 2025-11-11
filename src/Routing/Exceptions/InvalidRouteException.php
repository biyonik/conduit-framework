<?php 

declare(strict_types=1);

namespace Conduit\Routing\Exceptions;

use Exception;

/**
 * Invalid Route Exception
 * 
 * Route tanımı invalid olduğunda throw edilir.
 * 
 * @package Conduit\Routing\Exceptions
 */
class InvalidRouteException extends Exception
{
    /**
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Invalid route definition', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}