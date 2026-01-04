<?php

declare(strict_types=1);

namespace Conduit\Queue\Jobs;

use Conduit\Queue\Job;

class SendEmailJob extends Job
{
    public string $queue = 'emails';
    public int $tries = 3;
    public int $retryAfter = 300;
    
    public function __construct(
        protected string $to,
        protected string $subject,
        protected string $body
    ) {}
    
    public function handle(): void
    {
        // Send email using PHP's mail function
        // In production, you would use a proper email service
        mail($this->to, $this->subject, $this->body);
    }
    
    public function failed(\Throwable $exception): void
    {
        error_log("Failed to send email to {$this->to}: {$exception->getMessage()}");
    }
}
