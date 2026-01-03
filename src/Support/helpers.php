<?php

declare(strict_types=1);

/**
 * Global Helper Functions
 * 
 * Common utility functions available throughout the application.
 * These helpers provide convenient access to framework features.
 */

if (!function_exists('app')) {
    /**
     * Get the application container instance or resolve a service
     * 
     * @param string|null $abstract Service identifier to resolve
     * @param array<mixed> $parameters Constructor parameters
     * @return mixed|\Conduit\Core\Application
     */
    function app(?string $abstract = null, array $parameters = [])
    {
        $container = \Conduit\Core\Application::getInstance();
        if ($abstract === null) {
            return $container;
        }
        return $container->make($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        return app()->config($key, $default);
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable value
     * 
     * @param string $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }
        // Boolean conversion
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the base path of the application
     * 
     * @param string $path Path to append
     * @return string
     */
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage path of the application
     * 
     * @param string $path Path to append
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the configuration path of the application
     * 
     * @param string $path Path to append
     * @return string
     */
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (!function_exists('resource_path')) {
    /**
     * Get the resource path of the application
     * 
     * @param string $path Path to append
     * @return string
     */
    function resource_path(string $path = ''): string
    {
        return app()->resourcePath($path);
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the public path of the application
     * 
     * @param string $path Path to append
     * @return string
     */
    function public_path(string $path = ''): string
    {
        return app()->publicPath($path);
    }
}

if (!function_exists('database_path')) {
    /**
     * Get the database path of the application
     * 
     * @param string $path Path to append
     * @return string
     */
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}

if (!function_exists('event')) {
    /**
     * Dispatch an event
     * 
     * @param string|object $event Event name or object
     * @param mixed $payload Event payload
     * @return array<mixed>
     */
    function event(string|object $event, mixed $payload = null): array
    {
        return app(\Conduit\Events\Contracts\EventDispatcherInterface::class)->dispatch($event, $payload);
    }
}

if (!function_exists('value')) {
    /**
     * Return the value of the given value
     * 
     * @param mixed $value Value or closure
     * @param mixed ...$args Arguments to pass to closure
     * @return mixed
     */
    function value(mixed $value, ...$args): mixed
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation
     * 
     * @param mixed $target Array or object to search
     * @param string|array<string>|null $key Key in dot notation
     * @param mixed $default Default value
     * @return mixed
     */
    function data_get(mixed $target, string|array|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $target;
        }
        $key = is_array($key) ? $key : explode('.', $key);
        foreach ($key as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }
        return $target;
    }
}

if (!function_exists('data_set')) {
    /**
     * Set an item on an array or object using "dot" notation
     * 
     * @param mixed $target Array or object to modify
     * @param string|array<string> $key Key in dot notation
     * @param mixed $value Value to set
     * @param bool $overwrite Whether to overwrite existing values
     * @return mixed
     */
    function data_set(mixed &$target, string|array $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = is_array($key) ? $key : explode('.', $key);
        $segment = array_shift($segments);
        
        if (empty($segments)) {
            if ($overwrite || !isset($target[$segment])) {
                $target[$segment] = $value;
            }
        } else {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            data_set($target[$segment], $segments, $value, $overwrite);
        }
        
        return $target;
    }
}

if (!function_exists('collect')) {
    /**
     * Create a collection from the given value
     * 
     * @param array<mixed> $items Items to collect
     * @return \Conduit\Database\Collection
     */
    function collect(array $items = []): \Conduit\Database\Collection
    {
        return new \Conduit\Database\Collection($items);
    }
}

if (!function_exists('now')) {
    /**
     * Get the current date and time
     * 
     * @return \DateTimeImmutable
     */
    function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}

if (!function_exists('class_basename')) {
    /**
     * Get the class basename of the given object or class
     * 
     * @param string|object $class Class name or object
     * @return string
     */
    function class_basename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}
