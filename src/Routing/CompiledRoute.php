<?php

declare(strict_types=1);

namespace Conduit\Routing;

/**
 * Compiled Route
 * 
 * Immutable route data structure for cached routes.
 * Faster matching without object creation overhead.
 */
class CompiledRoute
{
    public function __construct(
        public readonly array $methods,
        public readonly string $uri,
        public readonly string $regex,
        public readonly array $parameters,
        public readonly string|array $action,
        public readonly array $middleware,
        public readonly ?string $name,
        public readonly ?string $domain,
        public readonly array $constraints,
    ) {}
    
    /**
     * Match request against this compiled route
     */
    public function matches(string $method, string $uri): ?array
    {
        // Method check
        if (!in_array($method, $this->methods, true)) {
            return null;
        }
        
        // URI regex match
        if (!preg_match($this->regex, $uri, $matches)) {
            return null;
        }
        
        // Extract parameters
        array_shift($matches); // Remove full match
        
        // Filter out empty optional parameters
        $matches = array_filter($matches, fn($match) => $match !== '');
        
        $parameters = [];
        if (!empty($this->parameters) && !empty($matches)) {
            // Combine parameter names with values
            $paramCount = min(count($this->parameters), count($matches));
            for ($i = 0; $i < $paramCount; $i++) {
                $parameters[$this->parameters[$i]] = $matches[$i];
            }
        }
        
        return [
            'action' => $this->action,
            'parameters' => $parameters,
            'middleware' => $this->middleware,
            'name' => $this->name,
        ];
    }
    
    /**
     * Create from compiled array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            methods: $data['methods'],
            uri: $data['uri'],
            regex: $data['regex'],
            parameters: $data['parameters'],
            action: $data['action'],
            middleware: $data['middleware'],
            name: $data['name'],
            domain: $data['domain'],
            constraints: $data['constraints'],
        );
    }
}
