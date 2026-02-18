<?php
/**
 * Test script to check edit page
 */

// Start session
session_start();

// Fake login
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'test';

// Set timezone
date_default_timezone_set('Europe/London');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load config
$appConfig = require __DIR__ . '/config/app.php';

// Set GET parameters
$_GET['id'] = 1;

echo "Testing edit page...\n\n";

try {
    // Include the edit page
    ob_start();
    include __DIR__ . '/views/customers/edit.php';
    $output = ob_get_clean();
    
    if (strpos($output, 'Edit Legal Entity') !== false) {
        echo "✅ SUCCESS: Edit page loaded correctly!\n";
        echo "Page contains 'Edit Legal Entity' heading\n";
    } else {
        echo "❌ FAILED: Edit page did not load correctly\n";
        echo "Output preview:\n";
        echo substr($output, 0, 500) . "...\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

