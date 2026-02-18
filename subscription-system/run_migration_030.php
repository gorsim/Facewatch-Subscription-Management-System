<?php
/**
 * Run Migration 030: Add match_score and match_breakdown columns
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "Running Migration 030: Add match_score and match_breakdown columns...\n\n";
    
    // Read and execute migration file
    $sql = file_get_contents(__DIR__ . '/migrations/030_add_match_score_breakdown.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        echo "Executing: " . substr($statement, 0, 100) . "...\n";
        $conn->exec($statement);
    }
    
    echo "\n✅ Migration 030 completed successfully!\n";
    echo "\nColumns added:\n";
    echo "  - match_score (DECIMAL 5,2)\n";
    echo "  - match_breakdown (JSON)\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

