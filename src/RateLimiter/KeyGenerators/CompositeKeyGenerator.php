<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\KeyGenerators;

use Conduit\Http\Request;
use Conduit\RateLimiter\Contracts\KeyGeneratorInterface;

class CompositeKeyGenerator implements KeyGeneratorInterface
{
    /**
     * @var KeyGeneratorInterface[]
     */
    protected array $generators;
    
    public function __construct(KeyGeneratorInterface ...$generators)
    {
        $this->generators = $generators;
    }
    
    public function generate(Request $request, string $prefix = ''): string
    {
        $parts = [];
        
        foreach ($this->generators as $generator) {
            $parts[] = $generator->generate($request, '');
        }
        
        return $prefix . sha1(implode('|', $parts));
    }
}
