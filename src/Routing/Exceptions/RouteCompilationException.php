<?php 

declare(strict_types=1);

namespace Conduit\Routing\Exceptions;

use Exception;

/**
 * Route Compilation Exception
 * 
 * Route pattern'i regex'e compile edilemediğinde throw edilir.
 * 
 * @package Conduit\Routing\Exceptions
 */
class RouteCompilationException extends Exception
{
    /** @var string Route pattern that failed to compile */
    private string $pattern;
    
    /**
     * @param string $pattern Route pattern
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(
        string $pattern,
        string $message = 'Route compilation failed',
        int $code = 0,
        ?Exception $previous = null
    ) {
        $this->pattern = $pattern;
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Failed pattern getter
     * 
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }
}