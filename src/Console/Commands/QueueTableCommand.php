<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Database\Schema\Blueprint;
use Conduit\Database\Schema\Schema;

class QueueTableCommand extends Command
{
    protected string $name = 'queue:table';
    protected string $description = 'Create the queue tables migration';
    
    public function handle(): int
    {
        $schema = app(Schema::class);
        
        // Check if tables already exist
        try {
            if ($this->tableExists('jobs')) {
                $this->warn('Jobs table already exists');
                return 1;
            }
        } catch (\Exception $e) {
            // Continue if we can't check
        }
        
        $this->info('Creating queue tables...');
        
        try {
            $schema->create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
                
                $table->index(['queue', 'reserved_at', 'available_at']);
            });
            
            $schema->create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->unsignedInteger('failed_at');
                
                $table->index('queue');
            });
            
            $this->success('Queue tables created successfully');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to create tables: ' . $e->getMessage());
            return 1;
        }
    }
    
    protected function tableExists(string $table): bool
    {
        try {
            $connection = app(\Conduit\Database\Connection::class);
            $result = $connection->select("SHOW TABLES LIKE '{$table}'");
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
}
