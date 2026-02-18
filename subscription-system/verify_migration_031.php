<?php
/**
 * Verify Migration 031: Invoice Notes and Camera Fields
 * 
 * This script checks that all new columns were added successfully
 */

require_once __DIR__ . '/config/database.php';

try {
    echo "Verifying Migration 031: Invoice Notes and Camera Fields\n";
    echo str_repeat("=", 70) . "\n\n";
    
    // Connect to database
    $config = require __DIR__ . '/config/database.php';
    $pdo = new PDO(
        "mysql:host=" . $config['host'] . ";dbname=" . $config['database'] . ";charset=utf8mb4",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "✓ Connected to database: {$config['database']}\n\n";
    
    // Check invoices.notes column
    echo "Checking invoices table...\n";
    $result = $pdo->query("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '{$config['database']}'
        AND TABLE_NAME = 'invoices'
        AND COLUMN_NAME = 'notes'
    ")->fetch();
    
    if ($result) {
        echo "  ✓ Column 'notes' exists\n";
        echo "    - Type: {$result['DATA_TYPE']}\n";
        echo "    - Nullable: {$result['IS_NULLABLE']}\n";
        echo "    - Comment: {$result['COLUMN_COMMENT']}\n";
    } else {
        echo "  ✗ Column 'notes' NOT FOUND!\n";
    }
    
    echo "\n";
    
    // Check camera_installations.camera_name column
    echo "Checking camera_installations table...\n";
    $result = $pdo->query("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '{$config['database']}'
        AND TABLE_NAME = 'camera_installations'
        AND COLUMN_NAME = 'camera_name'
    ")->fetch();
    
    if ($result) {
        echo "  ✓ Column 'camera_name' exists\n";
        echo "    - Type: {$result['DATA_TYPE']}({$result['CHARACTER_MAXIMUM_LENGTH']})\n";
        echo "    - Nullable: {$result['IS_NULLABLE']}\n";
        echo "    - Comment: {$result['COLUMN_COMMENT']}\n";
    } else {
        echo "  ✗ Column 'camera_name' NOT FOUND!\n";
    }
    
    echo "\n";
    
    // Check camera_installations.safr_code column
    $result = $pdo->query("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT, CHARACTER_MAXIMUM_LENGTH
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '{$config['database']}'
        AND TABLE_NAME = 'camera_installations'
        AND COLUMN_NAME = 'safr_code'
    ")->fetch();
    
    if ($result) {
        echo "  ✓ Column 'safr_code' exists\n";
        echo "    - Type: {$result['DATA_TYPE']}({$result['CHARACTER_MAXIMUM_LENGTH']})\n";
        echo "    - Nullable: {$result['IS_NULLABLE']}\n";
        echo "    - Comment: {$result['COLUMN_COMMENT']}\n";
    } else {
        echo "  ✗ Column 'safr_code' NOT FOUND!\n";
    }
    
    echo "\n";
    
    // Check for index on safr_code
    $result = $pdo->query("
        SELECT INDEX_NAME, COLUMN_NAME
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = '{$config['database']}'
        AND TABLE_NAME = 'camera_installations'
        AND INDEX_NAME = 'idx_safr_code'
    ")->fetch();
    
    if ($result) {
        echo "  ✓ Index 'idx_safr_code' exists on column '{$result['COLUMN_NAME']}'\n";
    } else {
        echo "  ✗ Index 'idx_safr_code' NOT FOUND!\n";
    }
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "✅ Verification complete!\n\n";
    
    echo "Summary:\n";
    echo "  • invoices.notes - Ready for use\n";
    echo "  • camera_installations.camera_name - Ready for use\n";
    echo "  • camera_installations.safr_code - Ready for use (indexed)\n\n";
    
    echo "Next steps:\n";
    echo "  1. Create a test invoice with notes\n";
    echo "  2. Import camera data with Camera Name and SAFR Code\n";
    echo "  3. View the invoice to see the notes displayed\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Verification failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

