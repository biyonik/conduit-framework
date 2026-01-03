<?php

declare(strict_types=1);

namespace Conduit\Console\Contracts;

/**
 * Input Interface
 * 
 * Contract for console input handling
 */
interface InputInterface
{
    /**
     * Get an argument by index
     * 
     * @param int $index Argument index (0 = command name)
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function getArgument(int $index, mixed $default = null): mixed;
    
    /**
     * Get all arguments
     * 
     * @return array<int, string>
     */
    public function getArguments(): array;
    
    /**
     * Get an option value
     * 
     * @param string $name Option name (without --)
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function getOption(string $name, mixed $default = null): mixed;
    
    /**
     * Check if an option exists
     * 
     * @param string $name Option name (without --)
     * @return bool
     */
    public function hasOption(string $name): bool;
    
    /**
     * Get all options
     * 
     * @return array<string, mixed>
     */
    public function getOptions(): array;
}
