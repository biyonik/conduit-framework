<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Make Command Command
 * 
 * Create a new console command class
 */
class MakeCommandCommand extends Command
{
    protected string $name = 'make:command';
    protected string $description = 'Create a new console command class';
    
    public function handle(): int
    {
        $name = $this->input->getArgument(1);
        
        if (!$name) {
            $this->error('Command name is required.');
            $this->line('  Usage: php conduit make:command SendEmailCommand');
            return 1;
        }
        
        // Remove "Command" suffix if provided, we'll add it
        $className = str_replace('Command', '', $name) . 'Command';
        
        // Generate command name (e.g., SendEmailCommand -> send:email)
        $commandName = $this->input->getOption('command') 
            ?? $this->generateCommandName($className);
        
        $stub = $this->getStub('command');
        $content = str_replace(
            ['{{name}}', '{{command}}'], 
            [$className, $commandName], 
            $stub
        );
        
        $path = base_path("app/Console/Commands/{$className}.php");
        
        if (file_exists($path)) {
            $this->error("Command already exists: {$className}");
            return 1;
        }
        
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($path, $content);
        
        $this->success("Command created: app/Console/Commands/{$className}.php");
        $this->info("Command name: {$commandName}");
        
        return 0;
    }
    
    /**
     * Generate command name from class name
     * e.g., SendEmailCommand -> send:email
     */
    protected function generateCommandName(string $className): string
    {
        // Remove "Command" suffix
        $name = str_replace('Command', '', $className);
        
        // Convert PascalCase to kebab-case
        $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name) ?? $name);
        
        // Convert first dash to colon for namespace
        $kebab = preg_replace('/-/', ':', $kebab, 1) ?? $kebab;
        
        return $kebab;
    }
}
