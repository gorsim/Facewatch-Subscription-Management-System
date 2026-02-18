<?php
/**
 * Add camera_installation_id column to invoice_camera_allocations table
 * This allows us to track individual camera allocations instead of just store-level counts
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "<h1>Adding camera_installation_id Column</h1>";

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
        echo "<p style='color: green;'>✓ Column camera_installation_id already exists!</p>";
    } else {
        echo "<p>Adding camera_installation_id column...</p>";
        
        // Add the column
        $db->query("
            ALTER TABLE invoice_camera_allocations
            ADD COLUMN camera_installation_id INT NULL AFTER store_id,
            ADD FOREIGN KEY (camera_installation_id) REFERENCES camera_installations(id) ON DELETE CASCADE,
            ADD INDEX idx_camera_installation (camera_installation_id)
        ");
        
        echo "<p style='color: green;'>✓ Column added successfully!</p>";
        
        // Make store_id nullable since we'll use camera_installation_id instead
        $db->query("
            ALTER TABLE invoice_camera_allocations
            MODIFY COLUMN store_id INT NULL
        ");
        
        echo "<p style='color: green;'>✓ Made store_id nullable for individual camera allocations</p>";
        
        // Drop the unique constraint on invoice_id + store_id
        $db->query("
            ALTER TABLE invoice_camera_allocations
            DROP INDEX unique_invoice_store
        ");
        
        echo "<p style='color: green;'>✓ Removed unique constraint to allow multiple cameras per invoice</p>";
    }
    
    // Show updated structure
    echo "<h2>Updated Table Structure:</h2>";
    $columns = $db->query("DESCRIBE invoice_camera_allocations");
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h2 style='color: green;'>✅ Migration Complete!</h2>";
    echo "<p><a href='index.php?page=invoices'>← Back to Invoices</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

