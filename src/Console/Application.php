<?php

declare(strict_types=1);

namespace Conduit\Console;

use Conduit\Console\Contracts\CommandInterface;
use Conduit\Console\Contracts\InputInterface;
use Conduit\Console\Contracts\OutputInterface;
use Conduit\Console\Input\Input;
use Conduit\Console\Output\ConsoleOutput;
use Conduit\Console\Output\BufferedOutput;
use Conduit\Core\Application as FrameworkApplication;
use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

/**
 * Console Application
 * 
 * Main console application class for CLI commands
 */
class Application
{
    protected FrameworkApplication $framework;
    
    /**
     * @var array<string, CommandInterface>
     */
    protected array $commands = [];
    
    protected InputInterface $input;
    protected OutputInterface $output;
    
    public function __construct(FrameworkApplication $framework)
    {
        $this->framework = $framework;
        $this->output = new ConsoleOutput();
    }
    
    /**
     * Register a command
     */
    public function add(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
        
        foreach ($command->getAliases() as $alias) {
            $this->commands[$alias] = $command;
        }
    }
    
    /**
     * Run console application
     * 
     * @param array<int, string>|null $argv
     */
    public function run(?array $argv = null): int
    {
        $argv = $argv ?? $_SERVER['argv'] ?? [];
        
        $this->input = Input::fromArgv($argv);
        
        $commandName = $this->input->getArgument(0) ?? 'list';
        
        return $this->call($commandName);
    }
    
    /**
     * Call a command
     * 
     * @param array<mixed> $arguments
     */
    public function call(string $name, array $arguments = []): int
    {
        if (!isset($this->commands[$name])) {
            $this->output->error("Command not found: {$name}");
            $this->output->writeln('');
            $this->output->writeln('Run <info>php conduit list</info> to see available commands.');
            return 1;
        }
        
        $command = $this->commands[$name];
        $command->setApplication($this);
        $command->setInput($this->input);
        $command->setOutput($this->output);
        
        try {
            return $command->execute();
        } catch (\Throwable $e) {
            $this->output->error($e->getMessage());
            
            if ($this->framework->config('app.debug', false)) {
                $this->output->writeln('');
                $this->output->writeln($e->getTraceAsString());
            }
            
            return 1;
        }
    }
    
    /**
     * Get all registered commands
     * 
     * @return array<CommandInterface>
     */
    public function getCommands(): array
    {
        return array_values(array_unique($this->commands, SORT_REGULAR));
    }
    
    /**
     * Run command via HTTP (shared hosting fallback)
     */
    public function runViaHttp(Request $request): JsonResponse
    {
        // Security: Validate token
        $token = $request->input('token');
        
        if (!$this->validateToken((string) $token)) {
            return JsonResponse::unauthorized('Invalid token');
        }
        
        $command = $request->input('command');
        $arguments = $request->input('arguments', []);
        
        // Use buffered output
        $bufferedOutput = new BufferedOutput();
        $originalOutput = $this->output;
        $this->output = $bufferedOutput;
        
        $exitCode = $this->call((string) $command, (array) $arguments);
        
        $output = $bufferedOutput->fetch();
        $this->output = $originalOutput;
        
        return JsonResponse::success([
            'command' => $command,
            'exit_code' => $exitCode,
            'output' => $output,
        ]);
    }
    
    /**
     * Validate CLI token for HTTP access
     */
    protected function validateToken(string $token): bool
    {
        $expected = $this->framework->config('app.cli_token');
        
        if (!$expected) {
            return false;
        }
        
        return hash_equals((string) $expected, $token);
    }
    
    /**
     * Get the framework instance
     */
    public function getFramework(): FrameworkApplication
    {
        return $this->framework;
    }
}
