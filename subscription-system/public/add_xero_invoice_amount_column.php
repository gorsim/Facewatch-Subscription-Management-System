<?php
/**
 * Migration: Add xero_invoice_amount column to invoices table
 * This stores the actual invoice amount from Xero for reconciliation comparison
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

header('Content-Type: text/plain');

try {
    $db = Database::getInstance();
    
    echo "Adding xero_invoice_amount Column\n";
    echo "===================================\n\n";
    
    // Add xero_invoice_amount column
    echo "Adding xero_invoice_amount column...\n";
    $db->query("
        ALTER TABLE invoices
        ADD COLUMN xero_invoice_amount DECIMAL(10,2) NULL
        COMMENT 'Actual invoice amount from Xero for reconciliation'
        AFTER xero_invoice_number
    ");
    
    echo "✅ Successfully added xero_invoice_amount column!\n\n";
    
    // Show the updated table structure
    echo "Updated invoices table structure (Xero-related columns):\n";
    $columns = $db->fetchAll("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
        AND TABLE_NAME = 'invoices'
        AND COLUMN_NAME LIKE '%xero%'
        ORDER BY ORDINAL_POSITION
    ");
    
    foreach ($columns as $col) {
        echo "  - {$col['COLUMN_NAME']} ({$col['COLUMN_TYPE']}) ";
        echo "NULL: {$col['IS_NULLABLE']} ";
        echo "COMMENT: {$col['COLUMN_COMMENT']}\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

