<?php
/**
 * Fix camera_counts_monthly table - Remove subscriber_id column
 * Run this from command line: php fix-camera-table.php
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/subscription-system/app/Database.php';

use App\Database;

echo "=== Fixing camera_counts_monthly table ===\n\n";

try {
    $db = Database::getInstance();
    echo "✅ Database connection successful\n\n";
    
    // Step 1: Show current structure
    echo "Current table structure:\n";
    $columns = $db->query("DESCRIBE camera_counts_monthly");
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) " . ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    echo "\n";
    
    // Step 2: Drop foreign key constraint first
    echo "Checking for foreign key constraints...\n";
    try {
        $db->query("ALTER TABLE camera_counts_monthly DROP FOREIGN KEY camera_counts_monthly_ibfk_1");
        echo "✅ Foreign key constraint removed successfully!\n\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "check that it exists") !== false || strpos($e->getMessage(), "check that column/key exists") !== false) {
            echo "⚠️  Foreign key constraint doesn't exist (already removed)\n\n";
        } else {
            echo "⚠️  " . $e->getMessage() . "\n\n";
        }
    }

    // Step 3: Drop unique index on subscriber_id
    echo "Checking for unique index on subscriber_id...\n";
    try {
        $db->query("ALTER TABLE camera_counts_monthly DROP INDEX unique_subscriber_month");
        echo "✅ Unique index removed successfully!\n\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "check that it exists") !== false || strpos($e->getMessage(), "check that column/key exists") !== false) {
            echo "⚠️  Unique index doesn't exist (already removed)\n\n";
        } else {
            echo "⚠️  " . $e->getMessage() . "\n\n";
        }
    }

    // Step 4: Remove subscriber_id column
    echo "Removing subscriber_id column...\n";
    try {
        $db->query("ALTER TABLE camera_counts_monthly DROP COLUMN subscriber_id");
        echo "✅ subscriber_id column removed successfully!\n\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "check that it exists") !== false || strpos($e->getMessage(), "check that column/key exists") !== false) {
            echo "⚠️  subscriber_id column doesn't exist (already removed)\n\n";
        } else {
            throw $e;
        }
    }
    
    // Step 5: Ensure store_id is NOT NULL
    echo "Ensuring store_id is properly configured...\n";
    try {
        $db->query("ALTER TABLE camera_counts_monthly MODIFY COLUMN store_id INT UNSIGNED NOT NULL");
        echo "✅ store_id configured successfully!\n\n";
    } catch (Exception $e) {
        echo "⚠️  " . $e->getMessage() . "\n\n";
    }
    
    // Step 6: Show updated structure
    echo "Updated table structure:\n";
    $columns = $db->query("DESCRIBE camera_counts_monthly");
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) " . ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    echo "\n";
    
    // Step 7: Check if subscriber_id still exists
    $hasSubscriberId = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'subscriber_id') {
            $hasSubscriberId = true;
            break;
        }
    }
    
    if ($hasSubscriberId) {
        echo "❌ ERROR: subscriber_id column still exists!\n";
        exit(1);
    } else {
        echo "✅ SUCCESS! The table is now fixed.\n";
        echo "You can now import camera count snapshots!\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

