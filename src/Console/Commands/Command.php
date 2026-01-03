<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Console\Application;
use Conduit\Console\Contracts\CommandInterface;
use Conduit\Console\Contracts\InputInterface;
use Conduit\Console\Contracts\OutputInterface;

/**
 * Base Command Class
 * 
 * Abstract base class for all console commands
 */
abstract class Command implements CommandInterface
{
    protected Application $application;
    protected InputInterface $input;
    protected OutputInterface $output;
    
    protected string $name = '';
    protected string $description = '';
    
    /**
     * @var array<string>
     */
    protected array $aliases = [];
    
    /**
     * Handle the command
     */
    abstract public function handle(): int;
    
    /**
     * {@inheritdoc}
     */
    public function setApplication(Application $application): void
    {
        $this->application = $application;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }
    
    /**
     * {@inheritdoc}
     */
    public function execute(): int
    {
        return $this->handle();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return $this->description;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }
    
    /**
     * Output info message
     */
    protected function info(string $message): void
    {
        $this->output->info($message);
    }
    
    /**
     * Output success message
     */
    protected function success(string $message): void
    {
        $this->output->success($message);
    }
    
    /**
     * Output error message
     */
    protected function error(string $message): void
    {
        $this->output->error($message);
    }
    
    /**
     * Output warning message
     */
    protected function warn(string $message): void
    {
        $this->output->warn($message);
    }
    
    /**
     * Output a line
     */
    protected function line(string $message = ''): void
    {
        $this->output->writeln($message);
    }
    
    /**
     * Display a table
     * 
     * @param array<string> $headers
     * @param array<array<string>> $rows
     */
    protected function table(array $headers, array $rows): void
    {
        $this->output->table($headers, $rows);
    }
    
    /**
     * Ask a question
     */
    protected function ask(string $question, ?string $default = null): string
    {
        $this->output->write("<question>{$question}</question> ");
        
        if ($default !== null) {
            $this->output->write("[{$default}] ");
        }
        
        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            return $default ?? '';
        }
        
        $answer = trim(fgets($handle) ?: '');
        fclose($handle);
        
        return $answer ?: ($default ?? '');
    }
    
    /**
     * Ask a yes/no question
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        $answer = $this->ask("{$question} ({$defaultText})", $default ? 'yes' : 'no');
        
        return in_array(strtolower($answer), ['y', 'yes', 'true', '1'], true);
    }
    
    /**
     * Get a stub file content
     */
    protected function getStub(string $name): string
    {
        $path = __DIR__ . '/../Stubs/' . $name . '.stub';
        
        if (!file_exists($path)) {
            throw new \RuntimeException("Stub not found: {$name}");
        }
        
        return file_get_contents($path) ?: '';
    }
    
    /**
     * Call another command
     */
    protected function call(string $command, array $arguments = []): int
    {
        return $this->application->call($command, $arguments);
    }
}
