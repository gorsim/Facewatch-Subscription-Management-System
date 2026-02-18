<?php
/**
 * Run Migration 028: Update Invoice Status Enum
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "=== Running Migration 028: Update Invoice Status Enum ===\n\n";

try {
    // Update the invoice_status enum
    echo "1. Updating invoice_status enum...\n";
    $db->query("
        ALTER TABLE invoices 
        MODIFY COLUMN invoice_status ENUM('draft', 'issued', 'reconciled_to_xero', 'cancelled', 'merged') 
        DEFAULT 'draft' 
        COMMENT 'Invoice lifecycle status - reconciled_to_xero means matched with Xero invoice'
    ");
    echo "   ✅ invoice_status enum updated\n\n";
    
    // Create status history table
    echo "2. Creating invoice_status_history table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS invoice_status_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            invoice_id INT NOT NULL,
            old_status ENUM('draft', 'issued', 'reconciled_to_xero', 'cancelled', 'merged'),
            new_status ENUM('draft', 'issued', 'reconciled_to_xero', 'cancelled', 'merged') NOT NULL,
            changed_by VARCHAR(100) NOT NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
            INDEX idx_invoice_status (invoice_id, changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ invoice_status_history table created\n\n";
    
    // Add reconciliation columns
    echo "3. Adding reconciliation tracking columns...\n";
    
    // Check if columns exist first
    $columns = $db->fetchAll("SHOW COLUMNS FROM invoices LIKE 'xero_invoice_id'");
    if (empty($columns)) {
        $db->query("ALTER TABLE invoices ADD COLUMN xero_invoice_id VARCHAR(100) NULL COMMENT 'Xero invoice ID for reconciliation'");
        echo "   ✅ Added xero_invoice_id column\n";
    } else {
        echo "   ⏭️  xero_invoice_id column already exists\n";
    }
    
    $columns = $db->fetchAll("SHOW COLUMNS FROM invoices LIKE 'xero_invoice_number'");
    if (empty($columns)) {
        $db->query("ALTER TABLE invoices ADD COLUMN xero_invoice_number VARCHAR(50) NULL COMMENT 'Invoice number in Xero'");
        echo "   ✅ Added xero_invoice_number column\n";
    } else {
        echo "   ⏭️  xero_invoice_number column already exists\n";
    }
    
    $columns = $db->fetchAll("SHOW COLUMNS FROM invoices LIKE 'reconciled_date'");
    if (empty($columns)) {
        $db->query("ALTER TABLE invoices ADD COLUMN reconciled_date DATE NULL COMMENT 'Date reconciled to Xero'");
        echo "   ✅ Added reconciled_date column\n";
    } else {
        echo "   ⏭️  reconciled_date column already exists\n";
    }
    
    $columns = $db->fetchAll("SHOW COLUMNS FROM invoices LIKE 'reconciled_by'");
    if (empty($columns)) {
        $db->query("ALTER TABLE invoices ADD COLUMN reconciled_by VARCHAR(100) NULL COMMENT 'User who reconciled'");
        echo "   ✅ Added reconciled_by column\n";
    } else {
        echo "   ⏭️  reconciled_by column already exists\n";
    }
    
    // Add indexes
    echo "\n4. Adding indexes...\n";
    try {
        $db->query("ALTER TABLE invoices ADD INDEX idx_xero_invoice_id (xero_invoice_id)");
        echo "   ✅ Added idx_xero_invoice_id index\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   ⏭️  idx_xero_invoice_id index already exists\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $db->query("ALTER TABLE invoices ADD INDEX idx_xero_invoice_number (xero_invoice_number)");
        echo "   ✅ Added idx_xero_invoice_number index\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   ⏭️  idx_xero_invoice_number index already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n✅ Migration 028 completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

