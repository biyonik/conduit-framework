<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * List Command
 * 
 * Lists all available console commands
 */
class ListCommand extends Command
{
    protected string $name = 'list';
    protected string $description = 'List all available commands';
    
    public function handle(): int
    {
        $this->info('Conduit Framework - Available Commands');
        $this->line();
        
        $commands = $this->application->getCommands();
        
        if (empty($commands)) {
            $this->warn('No commands registered.');
            return 0;
        }
        
        // Group commands by prefix
        $grouped = $this->groupCommands($commands);
        
        foreach ($grouped as $group => $cmds) {
            if ($group !== '') {
                $this->line("<comment>{$group}</comment>");
            }
            
            foreach ($cmds as $command) {
                $name = str_pad($command->getName(), 25);
                $description = $command->getDescription();
                $this->line("  <info>{$name}</info> {$description}");
            }
            
            $this->line();
        }
        
        return 0;
    }
    
    /**
     * Group commands by prefix
     * 
     * @param array<\Conduit\Console\Contracts\CommandInterface> $commands
     * @return array<string, array<\Conduit\Console\Contracts\CommandInterface>>
     */
    protected function groupCommands(array $commands): array
    {
        $grouped = [];
        
        foreach ($commands as $command) {
            $name = $command->getName();
            
            if (str_contains($name, ':')) {
                [$prefix] = explode(':', $name, 2);
            } else {
                $prefix = '';
            }
            
            $grouped[$prefix][] = $command;
        }
        
        ksort($grouped);
        
        return $grouped;
    }
}
