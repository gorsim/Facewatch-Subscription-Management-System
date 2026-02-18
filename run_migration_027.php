<?php
/**
 * Run Migration 027: Invoice Generation Foundation
 */

require_once __DIR__ . '/subscription-system/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "Running Migration 027: Invoice Generation Foundation\n\n";

try {
    // Read and execute the migration file
    $sql = file_get_contents(__DIR__ . '/database/migrations/027_invoice_generation_foundation.sql');
    
    // Split by semicolons and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) && 
                   !preg_match('/^USE /', $stmt);
        }
    );
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 80) . "...\n";
            $db->query($statement);
        }
    }
    
    echo "\n✅ Migration 027 complete!\n";
    echo "\nNew tables created:\n";
    echo "  - invoice_camera_allocations\n";
    echo "  - invoice_generation_log\n";
    echo "\nNew columns added to invoices:\n";
    echo "  - is_auto_generated\n";
    echo "  - parent_invoice_id\n";
    echo "  - generation_date\n";
    echo "  - next_generation_date\n";
    echo "  - invoice_status\n";
    echo "  - merged_into_invoice_id\n";
    echo "  - created_by\n";
    echo "  - notes\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

