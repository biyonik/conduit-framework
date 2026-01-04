<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Core\Container;
use Conduit\Core\ContainerCompiler;

/**
 * Container Compile Command
 * 
 * Compile the container for production
 */
class ContainerCompileCommand extends Command
{
    protected string $name = 'container:compile';
    protected string $description = 'Compile the container for faster dependency resolution';
    
    public function handle(): int
    {
        $this->info('Compiling container...');
        
        // Get container
        $container = app()->getContainer();
        
        if (!$container instanceof Container) {
            $this->error('Container is not compilable');
            return 1;
        }
        
        // Compile
        $compiler = new ContainerCompiler($container);
        
        try {
            $compiled = $compiler->compile();
        } catch (\RuntimeException $e) {
            $this->error('Compilation failed: ' . $e->getMessage());
            return 1;
        }
        
        // Save
        $cachePath = base_path('bootstrap/cache/container.php');
        $compiler->save($compiled, $cachePath);
        
        // Stats
        $bindingsCount = count($compiled['bindings']);
        $compilableCount = count(array_filter(
            $compiled['bindings'],
            fn($b) => $b['compilable'] ?? false
        ));
        
        $this->success('Container compiled successfully!');
        $this->line();
        $this->info("Total bindings: {$bindingsCount}");
        $this->info("Compilable: {$compilableCount}");
        $this->info("Closures: " . ($bindingsCount - $compilableCount));
        
        $size = filesize($cachePath);
        $this->info("Cache size: " . $this->formatBytes($size));
        
        return 0;
    }
    
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB'];
        $power = floor(($bytes > 0 ? log($bytes) : 0) / log(1024));
        $power = min($power, count($units) - 1);
        
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
