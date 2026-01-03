<?php

declare(strict_types=1);

namespace Conduit\Support;

/**
 * Dotenv
 * 
 * Simple .env file parser for environment variables.
 * Loads and parses .env files into PHP's environment.
 * 
 * Features:
 * - Comment support (lines starting with #)
 * - Quote handling (single and double quotes)
 * - Variable expansion (${VAR} or $VAR)
 * - Escape sequences in double-quoted strings
 * - Required variable validation
 * 
 * @package Conduit\Support
 */
class Dotenv
{
    /**
     * Base path for .env file
     */
    protected string $path;
    
    /**
     * Parsed environment variables
     * 
     * @var array<string, string>
     */
    protected array $variables = [];
    
    /**
     * Constructor
     * 
     * @param string $path Directory containing .env file
     */
    public function __construct(string $path)
    {
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
    }
    
    /**
     * Load and parse .env file
     * 
     * @param string $path Directory containing .env file
     * @return self
     */
    public static function load(string $path): self
    {
        $dotenv = new self($path);
        $dotenv->parse();
        return $dotenv;
    }
    
    /**
     * Parse .env file and set environment variables
     * 
     * @return void
     */
    public function parse(): void
    {
        $file = $this->path . DIRECTORY_SEPARATOR . '.env';
        
        if (!file_exists($file)) {
            return;
        }
        
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return;
        }
        
        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            
            // Parse KEY=value
            if (str_contains($line, '=')) {
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = $this->parseValue(trim($value));
                
                $this->variables[$name] = $value;
                
                // Set in environment
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                putenv("{$name}={$value}");
            }
        }
    }
    
    /**
     * Parse environment variable value
     * 
     * @param string $value Raw value from .env file
     * @return string Parsed value
     */
    protected function parseValue(string $value): string
    {
        // Remove quotes
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        
        // Handle escape sequences in double-quoted strings
        $value = str_replace(['\\n', '\\r', '\\t'], ["\n", "\r", "\t"], $value);
        
        // Variable expansion ${VAR} or $VAR
        $value = preg_replace_callback('/\$\{([^}]+)\}|\$([A-Z_][A-Z0-9_]*)/i', function ($matches) {
            $varName = $matches[1] ?? $matches[2];
            return $this->variables[$varName] ?? $_ENV[$varName] ?? $_SERVER[$varName] ?? getenv($varName) ?: '';
        }, $value);
        
        return $value;
    }
    
    /**
     * Get environment variable value
     * 
     * @param string $key Variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }
    
    /**
     * Get all parsed variables
     * 
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->variables;
    }
    
    /**
     * Validate that required variables are set
     * 
     * @param array<string> $keys Required variable names
     * @return void
     * @throws \RuntimeException If any required variables are missing
     */
    public function required(array $keys): void
    {
        $missing = [];
        foreach ($keys as $key) {
            if (!isset($this->variables[$key])) {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            throw new \RuntimeException(
                'Required environment variables are missing: ' . implode(', ', $missing)
            );
        }
    }
}
