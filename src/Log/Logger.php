<?php

declare(strict_types=1);

namespace Conduit\Log;

use Conduit\Log\Contracts\LoggerInterface;

/**
 * Logger - PSR-3 Compliant
 *
 * Shared hosting friendly file-based logger.
 *
 * @package Conduit\Log
 */
class Logger implements LoggerInterface
{
    protected string $channel;
    protected string $path;
    protected string $level;

    protected const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'notice' => 250,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
        'alert' => 550,
        'emergency' => 600,
    ];

    public function __construct(string $channel = 'app', string $path = '', string $level = 'debug')
    {
        $this->channel = $channel;
        $this->path = $path ?: storage_path('logs');
        $this->level = $level;

        if (!is_dir($this->path)) {
            @mkdir($this->path, 0755, true);
        }
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string|\Stringable $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $file = $this->path . '/' . $this->channel . '-' . date('Y-m-d') . '.log';
        $line = $this->format($level, $message, $context);

        file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        @chmod($file, 0644);
    }

    protected function shouldLog(string $level): bool
    {
        return self::LEVELS[$level] >= self::LEVELS[$this->level];
    }

    protected function format(string $level, string|\Stringable $message, array $context): string
    {
        $message = $this->interpolate((string)$message, $context);

        return sprintf(
            '[%s] %s.%s: %s %s',
            date('Y-m-d H:i:s'),
            $this->channel,
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context) : ''
        );
    }

    protected function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }
}
