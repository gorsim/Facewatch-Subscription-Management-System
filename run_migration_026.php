<?php
/**
 * Run Migration 026: Add payment_terms_days to legal_entities
 */

require_once __DIR__ . '/subscription-system/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "Running Migration 026: Add payment_terms_days to legal_entities\n\n";

try {
    // Check if column exists
    $columnExists = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
        AND TABLE_NAME = 'legal_entities' 
        AND COLUMN_NAME = 'payment_terms_days'
    ");

    if ($columnExists['count'] == 0) {
        echo "Adding payment_terms_days column...\n";
        $db->query("
            ALTER TABLE legal_entities 
            ADD COLUMN payment_terms_days INT DEFAULT 30 
            COMMENT 'Number of days until payment is expected' 
            AFTER payment_frequency
        ");
        echo "✅ Column payment_terms_days added successfully!\n";
    } else {
        echo "ℹ️  Column payment_terms_days already exists\n";
    }

    echo "\n✅ Migration 026 complete!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

