<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Make Middleware Command
 * 
 * Create a new middleware class
 */
class MakeMiddlewareCommand extends Command
{
    protected string $name = 'make:middleware';
    protected string $description = 'Create a new middleware class';
    
    public function handle(): int
    {
        $name = $this->input->getArgument(1);
        
        if (!$name) {
            $this->error('Middleware name is required.');
            $this->line('  Usage: php conduit make:middleware CheckPermission');
            return 1;
        }
        
        $stub = $this->getStub('middleware');
        $content = str_replace('{{name}}', $name, $stub);
        
        $path = base_path("app/Middleware/{$name}.php");
        
        if (file_exists($path)) {
            $this->error("Middleware already exists: {$name}");
            return 1;
        }
        
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($path, $content);
        
        $this->success("Middleware created: app/Middleware/{$name}.php");
        
        return 0;
    }
}
