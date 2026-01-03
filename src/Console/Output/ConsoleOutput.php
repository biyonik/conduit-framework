<?php

declare(strict_types=1);

namespace Conduit\Console\Output;

use Conduit\Console\Contracts\OutputInterface;

/**
 * Console Output
 * 
 * Handles formatted console output with ANSI colors
 */
class ConsoleOutput implements OutputInterface
{
    /**
     * ANSI color codes
     */
    protected const COLORS = [
        'reset' => "\033[0m",
        'black' => "\033[30m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'gray' => "\033[90m",
        'bright_red' => "\033[91m",
        'bright_green' => "\033[92m",
        'bright_yellow' => "\033[93m",
        'bright_blue' => "\033[94m",
        'bright_magenta' => "\033[95m",
        'bright_cyan' => "\033[96m",
        'bright_white' => "\033[97m",
    ];
    
    /**
     * ANSI styles
     */
    protected const STYLES = [
        'bold' => "\033[1m",
        'dim' => "\033[2m",
        'italic' => "\033[3m",
        'underline' => "\033[4m",
        'blink' => "\033[5m",
        'reverse' => "\033[7m",
        'hidden' => "\033[8m",
    ];
    
    /**
     * Tag to style mapping
     */
    protected const TAG_STYLES = [
        'info' => 'cyan',
        'comment' => 'yellow',
        'question' => 'cyan',
        'error' => 'red',
        'warning' => 'yellow',
        'success' => 'green',
    ];
    
    protected bool $decorated = true;
    
    public function __construct()
    {
        // Auto-detect color support
        $this->decorated = $this->hasColorSupport();
    }
    
    /**
     * {@inheritdoc}
     */
    public function write(string $message): void
    {
        echo $this->format($message);
    }
    
    /**
     * {@inheritdoc}
     */
    public function writeln(string $message = ''): void
    {
        echo $this->format($message) . PHP_EOL;
    }
    
    /**
     * {@inheritdoc}
     */
    public function info(string $message): void
    {
        $this->writeln("<info>{$message}</info>");
    }
    
    /**
     * {@inheritdoc}
     */
    public function success(string $message): void
    {
        $this->writeln("<success>✓ {$message}</success>");
    }
    
    /**
     * {@inheritdoc}
     */
    public function error(string $message): void
    {
        $this->writeln("<error>✗ {$message}</error>");
    }
    
    /**
     * {@inheritdoc}
     */
    public function warn(string $message): void
    {
        $this->writeln("<warning>⚠ {$message}</warning>");
    }
    
    /**
     * {@inheritdoc}
     */
    public function table(array $headers, array $rows): void
    {
        if (empty($headers) && empty($rows)) {
            return;
        }
        
        // Calculate column widths
        $widths = array_fill(0, count($headers), 0);
        
        foreach ($headers as $i => $header) {
            $widths[$i] = max($widths[$i], mb_strlen($header));
        }
        
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string) $cell));
            }
        }
        
        // Draw table
        $this->drawTableLine($widths, '┌', '┬', '┐');
        $this->drawTableRow($headers, $widths);
        $this->drawTableLine($widths, '├', '┼', '┤');
        
        foreach ($rows as $row) {
            $this->drawTableRow($row, $widths);
        }
        
        $this->drawTableLine($widths, '└', '┴', '┘');
    }
    
    /**
     * Draw a table line
     * 
     * @param array<int> $widths
     */
    protected function drawTableLine(array $widths, string $left, string $middle, string $right): void
    {
        $line = $left;
        
        foreach ($widths as $i => $width) {
            $line .= str_repeat('─', $width + 2);
            $line .= $i < count($widths) - 1 ? $middle : $right;
        }
        
        $this->writeln($line);
    }
    
    /**
     * Draw a table row
     * 
     * @param array<mixed> $cells
     * @param array<int> $widths
     */
    protected function drawTableRow(array $cells, array $widths): void
    {
        $line = '│';
        
        foreach ($widths as $i => $width) {
            $cell = $cells[$i] ?? '';
            $line .= ' ' . str_pad((string) $cell, $width) . ' │';
        }
        
        $this->writeln($line);
    }
    
    /**
     * Format message with tags
     */
    protected function format(string $message): string
    {
        if (!$this->decorated) {
            return $this->stripTags($message);
        }
        
        // Replace custom tags
        return preg_replace_callback(
            '/<(\w+)>(.*?)<\/\1>/',
            function ($matches) {
                $tag = $matches[1];
                $text = $matches[2];
                
                if (isset(self::TAG_STYLES[$tag])) {
                    $color = self::TAG_STYLES[$tag];
                    return $this->colorize($text, $color);
                }
                
                return $text;
            },
            $message
        ) ?? $message;
    }
    
    /**
     * Strip formatting tags
     */
    protected function stripTags(string $message): string
    {
        return preg_replace('/<(\w+)>(.*?)<\/\1>/', '$2', $message) ?? $message;
    }
    
    /**
     * Colorize text
     */
    protected function colorize(string $text, string $color): string
    {
        $colorCode = self::COLORS[$color] ?? '';
        $reset = self::COLORS['reset'];
        
        return $colorCode . $text . $reset;
    }
    
    /**
     * Check if terminal supports colors
     */
    protected function hasColorSupport(): bool
    {
        // Windows
        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('TERM') === 'xterm';
        }
        
        // Unix-like
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
    
    /**
     * Disable colors
     */
    public function disableDecoration(): void
    {
        $this->decorated = false;
    }
    
    /**
     * Enable colors
     */
    public function enableDecoration(): void
    {
        $this->decorated = true;
    }
}
