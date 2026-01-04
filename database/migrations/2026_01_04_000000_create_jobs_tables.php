<?php

declare(strict_types=1);

use Conduit\Database\Connection;
use Conduit\Database\Schema\Schema;
use Conduit\Database\Schema\Blueprint;
use Conduit\Database\Schema\Migration;

/**
 * Create Jobs Tables Migration
 * 
 * Creates the jobs and failed_jobs tables for the database queue system
 */
class CreateJobsTables extends Migration
{
    protected Connection $connection;
    
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Run the migrations
     * 
     * @return void
     */
    public function up(): void
    {
        $schema = new Schema($this->connection);
        
        // Jobs table
        $schema->create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->tinyInteger('attempts')->unsigned()->default(0);
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            
            $table->index(['queue', 'reserved_at', 'available_at']);
        });
        
        // Failed jobs table
        $schema->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->unsignedInteger('failed_at');
            
            $table->index('queue');
        });
    }
    
    /**
     * Reverse the migrations
     * 
     * @return void
     */
    public function down(): void
    {
        $schema = new Schema($this->connection);
        
        $schema->dropIfExists('failed_jobs');
        $schema->dropIfExists('jobs');
    }
}
