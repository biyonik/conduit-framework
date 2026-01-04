<?php

declare(strict_types=1);

namespace Conduit\Core;

use ReflectionClass;
use ReflectionParameter;
use ReflectionNamedType;

/**
 * Container Compiler
 * 
 * Analyzes container bindings and compiles them into optimized PHP code.
 * Eliminates all runtime reflection in production.
 */
class ContainerCompiler
{
    protected Container $container;
    protected array $compiled = [];
    protected array $resolving = [];
    
    public function __construct(Container $container)
    {
        $this->container = $container;
    }
    
    /**
     * Compile all container bindings
     */
    public function compile(): array
    {
        $bindings = $this->container->getBindings();
        $aliases = $this->container->getAliases();
        $singletons = $this->container->getSingletons();
        
        $compiled = [
            'version' => '1.0',
            'compiled_at' => time(),
            'php_version' => PHP_VERSION,
            'bindings' => [],
            'aliases' => $aliases,
            'singletons' => array_keys($singletons),
        ];
        
        foreach ($bindings as $abstract => $binding) {
            $compiled['bindings'][$abstract] = $this->compileBinding($abstract, $binding);
        }
        
        return $compiled;
    }
    
    /**
     * Compile single binding
     */
    protected function compileBinding(string $abstract, array $binding): array
    {
        $concrete = $binding['concrete'];
        $shared = $binding['shared'];
        
        // If closure, we can't compile it - keep as is
        if ($concrete instanceof \Closure) {
            return [
                'type' => 'closure',
                'shared' => $shared,
                'compilable' => false,
            ];
        }
        
        // If string class name, analyze it
        if (is_string($concrete) && class_exists($concrete)) {
            return $this->analyzeClass($concrete, $shared);
        }
        
        return [
            'type' => 'unknown',
            'shared' => $shared,
            'compilable' => false,
        ];
    }
    
    /**
     * Analyze class dependencies
     */
    protected function analyzeClass(string $class, bool $shared): array
    {
        // Circular dependency check
        if (isset($this->resolving[$class])) {
            throw new \RuntimeException("Circular dependency detected: {$class}");
        }
        
        $this->resolving[$class] = true;
        
        try {
            $reflector = new ReflectionClass($class);
            
            if (!$reflector->isInstantiable()) {
                return [
                    'type' => 'not_instantiable',
                    'shared' => $shared,
                    'compilable' => false,
                ];
            }
            
            $constructor = $reflector->getConstructor();
            
            if ($constructor === null) {
                // No dependencies - simple instantiation
                return [
                    'type' => 'simple',
                    'class' => $class,
                    'shared' => $shared,
                    'dependencies' => [],
                    'compilable' => true,
                ];
            }
            
            $dependencies = $this->analyzeDependencies($constructor->getParameters());
            
            return [
                'type' => 'complex',
                'class' => $class,
                'shared' => $shared,
                'dependencies' => $dependencies,
                'compilable' => true,
            ];
            
        } finally {
            unset($this->resolving[$class]);
        }
    }
    
    /**
     * Analyze constructor parameters
     */
    protected function analyzeDependencies(array $parameters): array
    {
        $dependencies = [];
        
        foreach ($parameters as $param) {
            $dependencies[] = $this->analyzeParameter($param);
        }
        
        return $dependencies;
    }
    
    /**
     * Analyze single parameter
     */
    protected function analyzeParameter(ReflectionParameter $param): array
    {
        $type = $param->getType();
        
        $dependency = [
            'name' => $param->getName(),
            'type' => null,
            'builtin' => true,
            'optional' => $param->isOptional(),
            'default' => null,
        ];
        
        if ($type instanceof ReflectionNamedType) {
            $dependency['type'] = $type->getName();
            $dependency['builtin'] = $type->isBuiltin();
        }
        
        if ($param->isOptional() && $param->isDefaultValueAvailable()) {
            $dependency['default'] = $param->getDefaultValue();
        }
        
        return $dependency;
    }
    
    /**
     * Save compiled container to file
     */
    public function save(array $compiled, string $path): void
    {
        $dir = dirname($path);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $code = $this->generateCode($compiled);
        
        // Use tempnam for secure temp file creation
        $tempFile = tempnam($dir, 'container_');
        
        if ($tempFile === false) {
            throw new \RuntimeException("Failed to create temporary file in: {$dir}");
        }
        
        try {
            // Write to temp file
            $result = file_put_contents($tempFile, $code, LOCK_EX);
            
            if ($result === false) {
                throw new \RuntimeException("Failed to write container cache to: {$tempFile}");
            }
            
            // Atomic rename
            if (!rename($tempFile, $path)) {
                throw new \RuntimeException("Failed to move container cache to: {$path}");
            }
            
            // OPcache optimization
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
            
            if (function_exists('opcache_compile_file')) {
                @opcache_compile_file($path);
            }
        } catch (\Throwable $e) {
            // Clean up temp file on error
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            throw $e;
        }
    }
    
    /**
     * Generate optimized PHP code
     */
    protected function generateCode(array $compiled): string
    {
        $code = "<?php\n\n";
        $code .= "// Container cache generated at " . date('Y-m-d H:i:s') . "\n";
        $code .= "// DO NOT EDIT THIS FILE MANUALLY\n";
        $code .= "// PHP Version: " . $compiled['php_version'] . "\n\n";
        
        $code .= "return " . $this->varExport($compiled, 1) . ";\n";
        
        return $code;
    }
    
    /**
     * Better var_export with short array syntax
     */
    protected function varExport(mixed $var, int $indent = 0): string
    {
        if (is_array($var)) {
            $indexed = array_keys($var) === range(0, count($var) - 1);
            $items = [];
            
            foreach ($var as $key => $value) {
                $exportedValue = $this->varExport($value, $indent + 1);
                
                if ($indexed) {
                    $items[] = $exportedValue;
                } else {
                    $exportedKey = is_string($key) ? var_export($key, true) : $key;
                    $items[] = "{$exportedKey} => {$exportedValue}";
                }
            }
            
            if (empty($items)) {
                return '[]';
            }
            
            $spaces = str_repeat('    ', $indent);
            $innerSpaces = str_repeat('    ', $indent + 1);
            
            return "[\n{$innerSpaces}" . implode(",\n{$innerSpaces}", $items) . ",\n{$spaces}]";
        }
        
        return var_export($var, true);
    }
}
