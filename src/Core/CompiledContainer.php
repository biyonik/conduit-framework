<?php

declare(strict_types=1);

namespace Conduit\Core;

/**
 * Compiled Container
 * 
 * Production-optimized container that uses pre-compiled dependency graph.
 * Zero reflection, maximum performance.
 */
class CompiledContainer extends Container
{
    protected array $compiled = [];
    protected bool $isCompiled = false;
    
    /**
     * Load compiled container
     */
    public function loadCompiled(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        
        $this->compiled = require $path;
        $this->isCompiled = true;
    }
    
    /**
     * Make instance (compiled version)
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        // If compiled version available, use it
        if ($this->isCompiled && isset($this->compiled['bindings'][$abstract])) {
            return $this->makeCompiled($abstract, $parameters);
        }
        
        // Fallback to parent (reflection-based)
        return parent::make($abstract, $parameters);
    }
    
    /**
     * Make instance from compiled metadata
     */
    protected function makeCompiled(string $abstract, array $parameters): mixed
    {
        $binding = $this->compiled['bindings'][$abstract];
        
        // Check if singleton already instantiated (avoid checking null)
        if ($binding['shared'] && isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        // Handle non-compilable bindings (closures)
        if (!$binding['compilable']) {
            return parent::make($abstract, $parameters);
        }
        
        // Simple class (no dependencies)
        if ($binding['type'] === 'simple') {
            $instance = new $binding['class']();
        }
        // Complex class (has dependencies)
        elseif ($binding['type'] === 'complex') {
            $instance = $this->buildComplex($binding, $parameters);
        }
        else {
            return parent::make($abstract, $parameters);
        }
        
        // Store singleton
        if ($binding['shared']) {
            $this->instances[$abstract] = $instance;
        }
        
        return $instance;
    }
    
    /**
     * Build complex class from compiled metadata
     */
    protected function buildComplex(array $binding, array $parameters): object
    {
        $dependencies = [];
        
        foreach ($binding['dependencies'] as $index => $dependency) {
            // Use provided parameter if available
            if (isset($parameters[$index])) {
                $dependencies[] = $parameters[$index];
                continue;
            }
            
            // Skip builtin types
            if ($dependency['builtin']) {
                if ($dependency['optional']) {
                    $dependencies[] = $dependency['default'];
                } else {
                    throw new \RuntimeException(
                        "Cannot resolve builtin parameter: {$dependency['name']}"
                    );
                }
                continue;
            }
            
            // Resolve dependency from container
            if ($dependency['type']) {
                $dependencies[] = $this->make($dependency['type']);
            } elseif ($dependency['optional']) {
                $dependencies[] = $dependency['default'];
            } else {
                throw new \RuntimeException(
                    "Cannot resolve parameter: {$dependency['name']}"
                );
            }
        }
        
        return new $binding['class'](...$dependencies);
    }
    
    /**
     * Check if container is compiled
     */
    public function isCompiled(): bool
    {
        return $this->isCompiled;
    }
    
    /**
     * Get compilation info
     */
    public function getCompilationInfo(): ?array
    {
        if (!$this->isCompiled) {
            return null;
        }
        
        return [
            'version' => $this->compiled['version'] ?? null,
            'compiled_at' => $this->compiled['compiled_at'] ?? null,
            'php_version' => $this->compiled['php_version'] ?? null,
            'bindings_count' => count($this->compiled['bindings'] ?? []),
            'compilable_count' => count(array_filter(
                $this->compiled['bindings'] ?? [],
                fn($b) => $b['compilable'] ?? false
            )),
        ];
    }
}
