<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Benchmark Command
 * 
 * Benchmark container performance
 */
class BenchmarkCommand extends Command
{
    protected string $name = 'benchmark';
    protected string $description = 'Benchmark container performance';
    
    public function handle(): int
    {
        $iterations = 1000;
        
        $this->info("Benchmarking container ({$iterations} iterations)...");
        $this->line();
        
        // Warmup
        for ($i = 0; $i < 10; $i++) {
            app(\Conduit\Routing\Router::class);
        }
        
        // Benchmark
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            app(\Conduit\Routing\Router::class);
        }
        
        $time = (microtime(true) - $start) * 1000;
        $avgTime = $time / $iterations;
        
        $this->success("Average resolution time: " . round($avgTime, 3) . "ms");
        
        // Show compilation status
        $container = app()->getContainer();
        if (method_exists($container, 'isCompiled') && $container->isCompiled()) {
            $this->info('Container: COMPILED ⚡');
            
            $info = $container->getCompilationInfo();
            $this->line("  Bindings: {$info['compilable_count']}/{$info['bindings_count']} compiled");
        } else {
            $this->warn('Container: DYNAMIC (run "php conduit container:compile")');
        }
        
        return 0;
    }
}
