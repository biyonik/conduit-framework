<?php

declare(strict_types=1);

namespace Conduit\Queue\Contracts;

/**
 * Job Interface
 * 
 * Defines the contract for queueable jobs
 */
interface JobInterface
{
    /**
     * Execute the job
     * 
     * @return void
     */
    public function handle(): void;
    
    /**
     * Get the number of times the job may be attempted
     * 
     * @return int Maximum attempts
     */
    public function tries(): int;
    
    /**
     * Get the number of seconds to wait before retrying
     * 
     * @return int Retry delay in seconds
     */
    public function retryAfter(): int;
    
    /**
     * Handle job failure
     * 
     * @param \Throwable $exception The exception that caused the failure
     * @return void
     */
    public function failed(\Throwable $exception): void;
}
