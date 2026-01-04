<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Container Clear Command
 * 
 * Remove the compiled container file
 */
class ContainerClearCommand extends Command
{
    protected string $name = 'container:clear';
    protected string $description = 'Remove the compiled container file';
    
    public function handle(): int
    {
        $cachePath = base_path('bootstrap/cache/container.php');
        
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->success('Container cache cleared!');
        } else {
            $this->info('No container cache to clear.');
        }
        
        return 0;
    }
}
