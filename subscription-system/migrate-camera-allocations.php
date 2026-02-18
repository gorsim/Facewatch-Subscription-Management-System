<?php
/**
 * Add camera_installation_id column to invoice_camera_allocations table
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "Migration: Add Individual Camera Tracking\n";
echo "==========================================\n\n";

try {
    // Check if column already exists
    $columns = $db->query("DESCRIBE invoice_camera_allocations");
    $hasColumn = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'camera_installation_id') {
            $hasColumn = true;
            break;
        }
    }
    
    if ($hasColumn) {
        echo "✓ Column camera_installation_id already exists!\n";
    } else {
        echo "Step 1: Adding camera_installation_id column...\n";
        
        // Add the column
        $db->query("
            ALTER TABLE invoice_camera_allocations
            ADD COLUMN camera_installation_id INT NULL AFTER store_id
        ");
        
        echo "✓ Column added successfully!\n";
        
        echo "\nStep 2: Adding foreign key constraint...\n";
        $db->query("
            ALTER TABLE invoice_camera_allocations
            ADD FOREIGN KEY (camera_installation_id) REFERENCES camera_installations(id) ON DELETE CASCADE
        ");
        
        echo "✓ Foreign key added!\n";
        
        echo "\nStep 3: Adding index...\n";
        $db->query("
            ALTER TABLE invoice_camera_allocations
            ADD INDEX idx_camera_installation (camera_installation_id)
        ");
        
        echo "✓ Index added!\n";
        
        echo "\nStep 4: Making store_id nullable...\n";
        $db->query("
            ALTER TABLE invoice_camera_allocations
            MODIFY COLUMN store_id INT NULL
        ");
        
        echo "✓ store_id is now nullable!\n";
        
        echo "\nStep 5: Removing unique constraint...\n";
        $db->query("
            ALTER TABLE invoice_camera_allocations
            DROP INDEX unique_invoice_store
        ");
        
        echo "✓ Unique constraint removed!\n";
    }
    
    echo "\n==========================================\n";
    echo "✅ Migration Complete!\n";
    echo "==========================================\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

