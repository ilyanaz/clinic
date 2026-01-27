<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
} else {
    // If Composer dependencies are not installed, redirect to PHP login page
    header('Location: /clinic/public/login.php');
    exit();
}

// Bootstrap Laravel and handle the request...
try {
    /** @var Application $app */
    $app = require_once __DIR__.'/../bootstrap/app.php';
    
    $app->handleRequest(Request::capture());
} catch (Exception $e) {
    // If Laravel fails to bootstrap, redirect to PHP login page
    error_log('Laravel bootstrap error: ' . $e->getMessage());
    header('Location: /clinic/public/login.php');
    exit();
}
