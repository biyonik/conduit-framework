<?php

declare(strict_types=1);

namespace Conduit\Console\Contracts;

use Conduit\Console\Application;

/**
 * Command Interface
 * 
 * Contract for console commands
 */
interface CommandInterface
{
    /**
     * Set the console application instance
     */
    public function setApplication(Application $application): void;
    
    /**
     * Set the input instance
     */
    public function setInput(InputInterface $input): void;
    
    /**
     * Set the output instance
     */
    public function setOutput(OutputInterface $output): void;
    
    /**
     * Execute the command
     */
    public function execute(): int;
    
    /**
     * Get the command name
     */
    public function getName(): string;
    
    /**
     * Get the command description
     */
    public function getDescription(): string;
    
    /**
     * Get command aliases
     * 
     * @return array<string>
     */
    public function getAliases(): array;
}
