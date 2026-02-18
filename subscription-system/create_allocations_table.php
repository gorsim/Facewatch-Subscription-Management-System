<?php
/**
 * Create invoice_camera_allocations table
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "Creating invoice_camera_allocations table...\n";

$sql = "CREATE TABLE IF NOT EXISTS invoice_camera_allocations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    store_id INT NOT NULL,
    allocation_date DATE NOT NULL COMMENT 'Date cameras were allocated to this invoice',
    main_cameras INT NOT NULL DEFAULT 0 COMMENT 'Number of main cameras allocated',
    additional_cameras INT NOT NULL DEFAULT 0 COMMENT 'Number of additional cameras allocated',
    main_camera_rate DECIMAL(10,2) NOT NULL COMMENT 'Rate per main camera at time of allocation',
    additional_camera_rate DECIMAL(10,2) NOT NULL COMMENT 'Rate per additional camera at time of allocation',
    subtotal DECIMAL(10,2) NOT NULL COMMENT 'Calculated subtotal for this store',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    
    INDEX idx_invoice (invoice_id),
    INDEX idx_store (store_id),
    INDEX idx_allocation_date (allocation_date),
    
    UNIQUE KEY unique_invoice_store (invoice_id, store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks which cameras from which stores are allocated to each invoice'";

try {
    $db->query($sql);
    echo "✓ Table created successfully\n";
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
}

