<?php
require_once __DIR__ . '/app/Database.php';

use App\Database;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "Running Migration 030: Add match_score and match_breakdown columns...\n\n";
    
    // Add match_score column
    echo "1. Adding match_score column...\n";
    $conn->exec("
        ALTER TABLE xero_imported_invoices
        ADD COLUMN match_score DECIMAL(5, 2) NULL COMMENT 'Match score percentage (0-100)' AFTER match_confidence
    ");
    echo "   ✅ match_score column added\n\n";
    
    // Add match_breakdown column
    echo "2. Adding match_breakdown column...\n";
    $conn->exec("
        ALTER TABLE xero_imported_invoices
        ADD COLUMN match_breakdown JSON NULL COMMENT 'Detailed breakdown of match scoring' AFTER match_score
    ");
    echo "   ✅ match_breakdown column added\n\n";
    
    // Copy existing match_confidence to match_score
    echo "3. Copying match_confidence to match_score...\n";
    $conn->exec("
        UPDATE xero_imported_invoices
        SET match_score = match_confidence
        WHERE match_confidence IS NOT NULL
    ");
    echo "   ✅ Data copied\n\n";
    
    echo "✅ Migration 030 completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

