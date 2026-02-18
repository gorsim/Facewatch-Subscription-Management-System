<?php
/**
 * Add camera_name column to camera_installations table
 * This allows tracking individual camera names like "Front door", "Back door", etc.
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "<h1>Adding camera_name Column</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
</style>";

try {
    // Check if column already exists
    $columns = $db->query("DESCRIBE camera_installations");
    $hasColumn = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'camera_name') {
            $hasColumn = true;
            break;
        }
    }
    
    if ($hasColumn) {
        echo "<p class='success'>✓ Column 'camera_name' already exists!</p>";
    } else {
        echo "<p>Adding camera_name column...</p>";
        
        // Add the column after camera_type
        $db->query("
            ALTER TABLE camera_installations
            ADD COLUMN camera_name VARCHAR(100) NULL
            COMMENT 'Individual camera name like Front door, Back door, etc.'
            AFTER camera_type
        ");
        
        echo "<p class='success'>✓ Column 'camera_name' added successfully!</p>";
    }
    
    echo "<hr>";
    echo "<h2>Current camera_installations Table Structure:</h2>";
    
    $structure = $db->query("DESCRIBE camera_installations");
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($structure as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<p><a href='?page=invoices&action=generator'>← Go to Invoice Generator</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

