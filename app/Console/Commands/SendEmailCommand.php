<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Conduit\Console\Commands\Command;

class SendEmailCommand extends Command
{
    /**
     * The name of the command.
     */
    protected string $name = 'send:email';
    
    /**
     * The description of the command.
     */
    protected string $description = 'Command description';
    
    /**
     * Execute the command.
     */
    public function handle(): int
    {
        $this->info('SendEmailCommand executed!');
        
        return 0;
    }
}
