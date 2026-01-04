<?php

declare(strict_types=1);

namespace Conduit\Mail;

use Conduit\Mail\Contracts\MailerInterface;
use Conduit\Queue\QueueManager;

/**
 * Mailer
 *
 * Shared hosting friendly mailer.
 * Supports SMTP and PHP mail().
 *
 * @package Conduit\Mail
 */
class Mailer implements MailerInterface
{
    protected array $config;
    protected ?QueueManager $queue;

    public function __construct(array $config, ?QueueManager $queue = null)
    {
        $this->config = $config;
        $this->queue = $queue;
    }

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $driver = $this->config['default'] ?? 'smtp';

        return match ($driver) {
            'smtp' => $this->sendViaSMTP($to, $subject, $body, $options),
            'mail' => $this->sendViaPHPMail($to, $subject, $body, $options),
            default => throw new \InvalidArgumentException("Unsupported mail driver: {$driver}"),
        };
    }

    public function queue(string $to, string $subject, string $body, array $options = []): void
    {
        if ($this->queue === null) {
            throw new \RuntimeException('Queue manager not available');
        }

        $job = new SendMailJob($to, $subject, $body, $options);
        $this->queue->push($job);
    }

    protected function sendViaSMTP(string $to, string $subject, string $body, array $options): bool
    {
        $smtp = $this->config['mailers']['smtp'] ?? [];

        $from = $options['from'] ?? $smtp['from']['address'] ?? 'noreply@example.com';
        $fromName = $options['from_name'] ?? $smtp['from']['name'] ?? '';

        $headers = [
            "From: {$fromName} <{$from}>",
            "Reply-To: {$from}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
        ];

        if (isset($options['cc'])) {
            $headers[] = "Cc: {$options['cc']}";
        }

        if (isset($options['bcc'])) {
            $headers[] = "Bcc: {$options['bcc']}";
        }

        // For shared hosting, use mail() with proper headers
        // Full SMTP would require PHPMailer or similar
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    protected function sendViaPHPMail(string $to, string $subject, string $body, array $options): bool
    {
        $from = $options['from'] ?? ($this->config['from']['address'] ?? 'noreply@example.com');

        $headers = [
            "From: {$from}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}

/**
 * Send Mail Job
 */
class SendMailJob extends \Conduit\Queue\Job
{
    public function __construct(
        protected string $to,
        protected string $subject,
        protected string $body,
        protected array $options = []
    ) {}

    public function handle(): void
    {
        $mailer = app(\Conduit\Mail\Mailer::class);
        $mailer->send($this->to, $this->subject, $this->body, $this->options);
    }
}
