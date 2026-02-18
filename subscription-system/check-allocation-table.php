<?php
/**
 * Check the structure of invoice_camera_allocations table
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "Checking invoice_camera_allocations table structure...\n\n";
    
    $columns = $db->fetchAll("DESCRIBE invoice_camera_allocations");
    
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ") " . $column['Null'] . " " . $column['Key'] . " " . $column['Default'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

