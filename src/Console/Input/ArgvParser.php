<?php

declare(strict_types=1);

namespace Conduit\Console\Input;

/**
 * Argv Parser
 * 
 * Parses command line arguments and options
 */
class ArgvParser
{
    /**
     * Parse argv array into Input
     * 
     * @param array<int, string> $argv
     * @return Input
     */
    public function parse(array $argv): Input
    {
        $input = new Input();
        $arguments = [];
        $options = [];
        
        // Skip script name (first element)
        array_shift($argv);
        
        foreach ($argv as $arg) {
            if ($this->isOption($arg)) {
                $this->parseOption($arg, $options);
            } else {
                $arguments[] = $arg;
            }
        }
        
        $input->setArguments($arguments);
        $input->setOptions($options);
        
        return $input;
    }
    
    /**
     * Check if argument is an option
     */
    protected function isOption(string $arg): bool
    {
        return str_starts_with($arg, '-');
    }
    
    /**
     * Parse an option into options array
     * 
     * @param string $arg
     * @param array<string, mixed> &$options
     */
    protected function parseOption(string $arg, array &$options): void
    {
        // Handle long options (--option or --option=value)
        if (str_starts_with($arg, '--')) {
            $this->parseLongOption(substr($arg, 2), $options);
            return;
        }
        
        // Handle short options (-o or -abc)
        if (str_starts_with($arg, '-')) {
            $this->parseShortOption(substr($arg, 1), $options);
            return;
        }
    }
    
    /**
     * Parse long option (--option or --option=value)
     * 
     * @param string $option
     * @param array<string, mixed> &$options
     */
    protected function parseLongOption(string $option, array &$options): void
    {
        if (str_contains($option, '=')) {
            [$name, $value] = explode('=', $option, 2);
            $options[$name] = $value;
        } else {
            $options[$option] = true;
        }
    }
    
    /**
     * Parse short option (-o or -abc)
     * 
     * @param string $option
     * @param array<string, mixed> &$options
     */
    protected function parseShortOption(string $option, array &$options): void
    {
        // For simplicity, treat each character as a boolean flag
        $length = strlen($option);
        for ($i = 0; $i < $length; $i++) {
            $options[$option[$i]] = true;
        }
    }
}
