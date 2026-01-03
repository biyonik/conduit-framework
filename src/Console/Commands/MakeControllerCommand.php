<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Make Controller Command
 * 
 * Create a new controller class
 */
class MakeControllerCommand extends Command
{
    protected string $name = 'make:controller';
    protected string $description = 'Create a new controller class';
    
    public function handle(): int
    {
        $name = $this->input->getArgument(1);
        
        if (!$name) {
            $this->error('Controller name is required.');
            $this->line('  Usage: php conduit make:controller UserController');
            return 1;
        }
        
        // Remove "Controller" suffix if provided, we'll add it
        $name = str_replace('Controller', '', $name) . 'Controller';
        
        $stub = $this->getStub('controller');
        $content = str_replace('{{name}}', $name, $stub);
        
        $path = base_path("app/Controllers/{$name}.php");
        
        if (file_exists($path)) {
            $this->error("Controller already exists: {$name}");
            return 1;
        }
        
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($path, $content);
        
        $this->success("Controller created: app/Controllers/{$name}.php");
        
        return 0;
    }
}
