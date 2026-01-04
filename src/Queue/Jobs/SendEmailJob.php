<?php

declare(strict_types=1);

namespace Conduit\Queue\Jobs;

use Conduit\Queue\Job;

/**
 * Send Email Job Example
 * 
 * Example job that sends an email
 */
class SendEmailJob extends Job
{
    public string $queue = 'emails';
    public int $tries = 3;
    public int $retryAfter = 300; // 5 minutes
    
    /**
     * Create a new job instance
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body
     */
    public function __construct(
        protected string $to,
        protected string $subject,
        protected string $body
    ) {}
    
    /**
     * Execute the job
     * 
     * NOTE: In production, validate and sanitize email parameters to prevent
     * email header injection attacks. Consider using a proper email library
     * like PHPMailer or Symfony Mailer instead of the mail() function.
     * 
     * @return void
     */
    public function handle(): void
    {
        // Send email logic
        mail($this->to, $this->subject, $this->body);
    }
    
    /**
     * Handle job failure
     * 
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        // Log or notify admin about failed email
        error_log("Failed to send email to {$this->to}: {$exception->getMessage()}");
    }
}
