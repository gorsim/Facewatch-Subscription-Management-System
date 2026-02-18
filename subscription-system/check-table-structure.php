<?php
/**
 * Check camera_counts_monthly table structure
 */

require_once __DIR__ . '/app/Database.php';

$db = new Database();

echo "<h1>Camera Counts Monthly Table Structure</h1>";

try {
    $columns = $db->query("DESCRIBE camera_counts_monthly");
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Check if subscriber_id exists
    $hasSubscriberId = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'subscriber_id') {
            $hasSubscriberId = true;
            break;
        }
    }
    
    if ($hasSubscriberId) {
        echo "<h2 style='color: red;'>⚠️ Problem Found!</h2>";
        echo "<p>The table still has a <code>subscriber_id</code> column that needs to be removed.</p>";
        echo "<p>This column is from the old schema before we restructured to use stores.</p>";
        
        echo "<h3>Fix Required:</h3>";
        echo "<p>We need to run a migration to remove the <code>subscriber_id</code> column.</p>";
    } else {
        echo "<h2 style='color: green;'>✅ Table Structure Looks Good</h2>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

