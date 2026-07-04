<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

try {
    error_log("Incoming Request: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . " " . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN'));
    $app->handleRequest(Request::capture());
} catch (\Throwable $e) {
    error_log("UNCAUGHT EXCEPTION in index.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log($e->getTraceAsString());
    throw $e;
}
