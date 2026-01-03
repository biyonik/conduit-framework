<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Make Model Command
 * 
 * Create a new model class
 */
class MakeModelCommand extends Command
{
    protected string $name = 'make:model';
    protected string $description = 'Create a new model class';
    
    public function handle(): int
    {
        $name = $this->input->getArgument(1);
        
        if (!$name) {
            $this->error('Model name is required.');
            $this->line('  Usage: php conduit make:model User');
            return 1;
        }
        
        $stub = $this->getStub('model');
        
        // Convert model name to table name (e.g., User -> users, BlogPost -> blog_posts)
        $tableName = $this->getTableName($name);
        
        $content = str_replace(['{{name}}', '{{table}}'], [$name, $tableName], $stub);
        
        $path = base_path("app/Models/{$name}.php");
        
        if (file_exists($path)) {
            $this->error("Model already exists: {$name}");
            return 1;
        }
        
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($path, $content);
        
        $this->success("Model created: app/Models/{$name}.php");
        
        return 0;
    }
    
    /**
     * Convert model name to table name
     */
    protected function getTableName(string $name): string
    {
        // Convert PascalCase to snake_case and pluralize
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name);
        
        // Simple pluralization (add 's' if not ending with 's')
        if (!str_ends_with($snakeCase, 's')) {
            $snakeCase .= 's';
        }
        
        return $snakeCase;
    }
}
