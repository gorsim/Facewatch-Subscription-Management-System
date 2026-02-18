<?php
/**
 * Migration: Add rate_applied and subtotal columns to invoice_camera_allocations
 * 
 * This adds the columns needed for individual camera allocation tracking
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

try {
    $db = Database::getInstance();

    echo "Adding rate_applied and subtotal columns to invoice_camera_allocations table...\n";
    
    // Add rate_applied column
    $db->query("
        ALTER TABLE invoice_camera_allocations
        ADD COLUMN rate_applied DECIMAL(10,2) NULL AFTER camera_installation_id
    ");
    echo "✓ Added rate_applied column\n";
    
    // Add subtotal column
    $db->query("
        ALTER TABLE invoice_camera_allocations
        ADD COLUMN subtotal DECIMAL(10,2) NULL AFTER rate_applied
    ");
    echo "✓ Added subtotal column\n";
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

