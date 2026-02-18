<?php
/**
 * Run Migration 003: Fix camera_counts_monthly table
 */

require_once __DIR__ . '/app/Database.php';

$db = new Database();

echo "<h1>Running Migration 003: Fix camera_counts_monthly</h1>";

try {
    // Read the migration file
    $migrationFile = __DIR__ . '/../database/migrations/003_fix_camera_counts_monthly.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: {$migrationFile}");
    }
    
    $sql = file_get_contents($migrationFile);
    
    echo "<h2>Migration SQL:</h2>";
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";
    
    echo "<h2>Executing...</h2>";
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        // Skip comments and empty statements
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        echo "<p>Executing: <code>" . htmlspecialchars(substr($statement, 0, 100)) . "...</code></p>";
        
        try {
            $db->query($statement);
            echo "<p style='color: green;'>✅ Success</p>";
        } catch (Exception $e) {
            // Some statements might fail if column doesn't exist, etc - that's OK
            echo "<p style='color: orange;'>⚠️ " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "<h2 style='color: green;'>✅ Migration Complete!</h2>";
    
    // Show the updated table structure
    echo "<h2>Updated Table Structure:</h2>";
    $columns = $db->query("DESCRIBE camera_counts_monthly");
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    foreach ($columns as $column) {
        $highlight = ($column['Field'] === 'subscriber_id') ? "style='background-color: #ffcccc;'" : "";
        echo "<tr {$highlight}>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Check if subscriber_id still exists
    $hasSubscriberId = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'subscriber_id') {
            $hasSubscriberId = true;
            break;
        }
    }
    
    if ($hasSubscriberId) {
        echo "<p style='color: red; font-weight: bold;'>❌ subscriber_id column still exists! Manual intervention needed.</p>";
    } else {
        echo "<p style='color: green; font-weight: bold;'>✅ subscriber_id column successfully removed!</p>";
        echo "<p>You can now try importing camera counts again.</p>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

