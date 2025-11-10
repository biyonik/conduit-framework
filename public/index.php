<?php

/**
 * CONDUIT PHP FRAMEWORK
 * Entry Point - Front Controller
 *
 * All HTTP requests are routed through this file.
 * This is the single entry point for the entire application.
 */

// ============================================
// Define Constants
// ============================================
define('CONDUIT_START', microtime(true));

// ============================================
// Register Autoloader
// ============================================
require_once __DIR__ . '/../vendor/autoload.php';

// ============================================
// Bootstrap Application
// ============================================
$app = require_once __DIR__ . '/../bootstrap/app.php';

// ============================================
// Handle Request
// ============================================
$kernel = $app->make(Conduit\Http\Kernel::class);

$request = Conduit\Http\Request::capture();

$response = $kernel->handle($request);

// ============================================
// Send Response
// ============================================
$response->send();

// ============================================
// Terminate Application
// ============================================
$kernel->terminate($request, $response);
