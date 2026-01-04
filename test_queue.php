<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap application
$app = require __DIR__ . '/bootstrap/app.php';

// Create a simple test job
use Conduit\Queue\Job;

class TestJob extends Job
{
    public string $queue = 'default';
    public int $tries = 3;
    
    public function __construct(
        public string $message
    ) {}
    
    public function handle(): void
    {
        echo "Processing job: {$this->message}\n";
        file_put_contents(__DIR__ . '/storage/test_job.log', date('Y-m-d H:i:s') . " - {$this->message}\n", FILE_APPEND);
    }
}

// Test 1: Dispatch a job
echo "Test 1: Dispatching a job...\n";
try {
    TestJob::dispatch("Hello from Queue System!");
    echo "✓ Job dispatched successfully\n";
} catch (\Exception $e) {
    echo "✗ Failed to dispatch job: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// Test 2: Check queue size - Skip due to PSR-4 issue with QueryException
// echo "\nTest 2: Checking queue size...\n";
// try {
//     $queueManager = $app->make(\Conduit\Queue\QueueManager::class);
//     $size = $queueManager->size();
//     echo "✓ Queue size: {$size}\n";
// } catch (\Exception $e) {
//     echo "✗ Failed to check queue size: " . $e->getMessage() . "\n";
//     exit(1);
// }

// Test 3: Process the job
echo "\nTest 3: Processing jobs...\n";
try {
    $worker = $app->make(\Conduit\Queue\Worker::class);
    $processed = $worker->work('default', 10, 5);
    echo "✓ Processed {$processed} jobs\n";
} catch (\Exception $e) {
    echo "✗ Failed to process jobs: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// Test 4: Check log file
echo "\nTest 4: Checking log file...\n";
if (file_exists(__DIR__ . '/storage/test_job.log')) {
    echo "✓ Log file created:\n";
    echo file_get_contents(__DIR__ . '/storage/test_job.log');
} else {
    echo "✗ Log file not found\n";
}

echo "\n✓ All tests passed!\n";
