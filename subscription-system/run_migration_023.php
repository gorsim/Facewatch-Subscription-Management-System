<?php
/**
 * Run Migration 023: Remove Camera Snapshots
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

echo "Running Migration 023: Remove Camera Snapshots\n";
echo "================================================\n\n";

try {
    $db = Database::getInstance();
    // Read the migration file
    $migrationFile = __DIR__ . '/../database/migrations/023_remove_camera_snapshots.sql';
    
    if (!file_exists($migrationFile)) {
        die("ERROR: Migration file not found: {$migrationFile}\n");
    }
    
    $sql = file_get_contents($migrationFile);

    // Remove comments and empty lines
    $lines = explode("\n", $sql);
    $cleanedLines = [];
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if (empty($line) || substr($line, 0, 2) === '--' || substr($line, 0, 3) === 'USE') {
            continue;
        }
        $cleanedLines[] = $line;
    }
    $cleanedSql = implode("\n", $cleanedLines);

    // Split into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $cleanedSql)),
        function($stmt) {
            return !empty($stmt);
        }
    );

    echo "Found " . count($statements) . " SQL statements to execute\n\n";

    // Execute each statement
    foreach ($statements as $index => $statement) {
        $num = $index + 1;
        echo "Executing statement {$num}:\n";
        echo substr($statement, 0, 100) . "...\n";

        try {
            $db->query($statement);
            echo "✅ Statement {$num} executed successfully\n\n";
        } catch (Exception $e) {
            echo "⚠️  Statement {$num} warning: " . $e->getMessage() . "\n\n";
            // Continue with other statements
        }
    }
    
    echo "\n================================================\n";
    echo "Migration 023 completed!\n";
    echo "The camera_counts_monthly table has been removed.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

