<?php

declare(strict_types=1);

namespace Conduit\Console\Output;

use Conduit\Console\Contracts\OutputInterface;

/**
 * Buffered Output
 * 
 * Captures output in memory for later retrieval
 * Used for HTTP fallback
 */
class BufferedOutput implements OutputInterface
{
    protected string $buffer = '';
    
    /**
     * {@inheritdoc}
     */
    public function write(string $message): void
    {
        $this->buffer .= $this->stripAnsi($message);
    }
    
    /**
     * {@inheritdoc}
     */
    public function writeln(string $message = ''): void
    {
        $this->buffer .= $this->stripAnsi($message) . PHP_EOL;
    }
    
    /**
     * {@inheritdoc}
     */
    public function info(string $message): void
    {
        $this->writeln("[INFO] {$message}");
    }
    
    /**
     * {@inheritdoc}
     */
    public function success(string $message): void
    {
        $this->writeln("[SUCCESS] {$message}");
    }
    
    /**
     * {@inheritdoc}
     */
    public function error(string $message): void
    {
        $this->writeln("[ERROR] {$message}");
    }
    
    /**
     * {@inheritdoc}
     */
    public function warn(string $message): void
    {
        $this->writeln("[WARNING] {$message}");
    }
    
    /**
     * {@inheritdoc}
     */
    public function table(array $headers, array $rows): void
    {
        if (empty($headers) && empty($rows)) {
            return;
        }
        
        // Simple text-based table
        $this->writeln(implode(' | ', $headers));
        $this->writeln(str_repeat('-', 50));
        
        foreach ($rows as $row) {
            $this->writeln(implode(' | ', $row));
        }
    }
    
    /**
     * Get buffered content and clear buffer
     */
    public function fetch(): string
    {
        $content = $this->buffer;
        $this->buffer = '';
        return $content;
    }
    
    /**
     * Get buffered content without clearing
     */
    public function getBuffer(): string
    {
        return $this->buffer;
    }
    
    /**
     * Clear the buffer
     */
    public function clear(): void
    {
        $this->buffer = '';
    }
    
    /**
     * Strip ANSI codes from text
     */
    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
    }
}
