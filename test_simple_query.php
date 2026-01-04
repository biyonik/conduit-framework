<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';

try {
    $db = $app->make(\Conduit\Database\Connection::class);
    
    $query = $db->table('jobs')
        ->where('queue', 'default')
        ->where('available_at', '<=', time())
        ->whereNull('reserved_at')
        ->orderBy('id', 'asc');
    
    // Get the SQL
    echo "SQL: " . $query->toSql() . "\n";
    echo "Bindings: " . print_r($query->getBindings(), true) . "\n";
    
    $result = $query->first();
    var_dump($result);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
