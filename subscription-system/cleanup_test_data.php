<?php
/**
 * Clean up test import data
 */

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

use App\Database;

$db = Database::getInstance();

echo "Cleaning up test data...\n";

$result = $db->query("DELETE FROM legal_entities WHERE legal_entity_id LIKE 'TEST%'");

echo "✅ Test data cleaned up\n";

