<?php

declare(strict_types=1);

namespace Conduit\Console\Contracts;

/**
 * Output Interface
 * 
 * Contract for console output handling
 */
interface OutputInterface
{
    /**
     * Write a message
     */
    public function write(string $message): void;
    
    /**
     * Write a message with newline
     */
    public function writeln(string $message = ''): void;
    
    /**
     * Write an info message
     */
    public function info(string $message): void;
    
    /**
     * Write a success message
     */
    public function success(string $message): void;
    
    /**
     * Write an error message
     */
    public function error(string $message): void;
    
    /**
     * Write a warning message
     */
    public function warn(string $message): void;
    
    /**
     * Display a table
     * 
     * @param array<string> $headers
     * @param array<array<string>> $rows
     */
    public function table(array $headers, array $rows): void;
}
