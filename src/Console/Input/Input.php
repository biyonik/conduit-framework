<?php

declare(strict_types=1);

namespace Conduit\Console\Input;

use Conduit\Console\Contracts\InputInterface;

/**
 * Console Input
 * 
 * Handles command line input parsing
 */
class Input implements InputInterface
{
    /**
     * @var array<int, string>
     */
    protected array $arguments = [];
    
    /**
     * @var array<string, mixed>
     */
    protected array $options = [];
    
    /**
     * Create input from argv array
     * 
     * @param array<int, string> $argv
     * @return self
     */
    public static function fromArgv(array $argv): self
    {
        $parser = new ArgvParser();
        return $parser->parse($argv);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getArgument(int $index, mixed $default = null): mixed
    {
        return $this->arguments[$index] ?? $default;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
    
    /**
     * Set arguments
     * 
     * @param array<int, string> $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }
    
    /**
     * Set options
     * 
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
