<?php

declare(strict_types=1);

namespace Conduit\Mail\Contracts;

interface MailerInterface
{
    public function send(string $to, string $subject, string $body, array $options = []): bool;
    public function queue(string $to, string $subject, string $body, array $options = []): void;
}
