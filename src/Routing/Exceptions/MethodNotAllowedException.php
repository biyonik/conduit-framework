<?php 

declare(strict_types=1);

namespace Conduit\Routing\Exceptions;

use Exception;

/**
 * Method Not Allowed Exception
 * 
 * Route mevcut ama HTTP method allowed değilse throw edilir.
 * HTTP 405 Method Not Allowed response için kullanılır.
 * 
 * @package Conduit\Routing\Exceptions
 */
class MethodNotAllowedException extends Exception
{
    /** @var array<string> Allowed HTTP methods */
    private array $allowedMethods;
    
    /**
     * @param string $message Exception message
     * @param array<string> $allowedMethods Allowed HTTP methods
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Method not allowed',
        array $allowedMethods = [],
        int $code = 405,
        ?Exception $previous = null
    ) {
        $this->allowedMethods = $allowedMethods;
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Allowed methods getter
     * 
     * @return array<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
    
    /**
     * Allow header value için method'ları format et
     * 
     * @return string Comma-separated methods
     */
    public function getAllowHeader(): string
    {
        return implode(', ', $this->allowedMethods);
    }
}