-- Invoice Reconciliation System Redesign
-- Date: 2026-02-15
-- Purpose: Change invoice matching from invoice number to Xero Customer Number + Date
--          Add camera allocation tracking and value-based reconciliation

USE facewatch_subscriptions;

-- ============================================================================
-- STEP 1: Rename fcst_lookup_code to xero_customer_number
-- ============================================================================

-- Check if column exists before renaming
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' 
    AND COLUMN_NAME = 'fcst_lookup_code'
);

-- Rename the column if it exists
SET @sql = IF(@column_exists > 0,
    'ALTER TABLE legal_entities CHANGE COLUMN fcst_lookup_code xero_customer_number VARCHAR(255) NULL',
    'SELECT "Column fcst_lookup_code does not exist, skipping rename" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on xero_customer_number for faster lookups
CREATE INDEX IF NOT EXISTS idx_xero_customer_number ON legal_entities(xero_customer_number);

-- ============================================================================
-- STEP 2: Update invoices table
-- ============================================================================

-- Add xero_customer_number column to invoices table
ALTER TABLE invoices 
ADD COLUMN IF NOT EXISTS xero_customer_number VARCHAR(255) NULL AFTER legal_entity_id,
ADD COLUMN IF NOT EXISTS expected_amount DECIMAL(10,2) NULL COMMENT 'Expected invoice amount based on camera counts',
ADD COLUMN IF NOT EXISTS variance DECIMAL(10,2) NULL COMMENT 'Difference between invoice_amount and expected_amount',
ADD COLUMN IF NOT EXISTS reconciliation_status ENUM('matched', 'under_charged', 'over_charged', 'pending') DEFAULT 'pending' COMMENT 'Reconciliation status based on variance';

-- Populate xero_customer_number from legal_entities
UPDATE invoices i
JOIN legal_entities le ON i.legal_entity_id = le.id
SET i.xero_customer_number = le.xero_customer_number
WHERE i.xero_customer_number IS NULL;

-- Add unique constraint on xero_customer_number + invoice_date
-- Drop existing constraint if it exists
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
    AND TABLE_NAME = 'invoices'
    AND CONSTRAINT_NAME = 'unique_xero_invoice'
);

SET @sql = IF(@constraint_exists > 0,
    'ALTER TABLE invoices DROP INDEX unique_xero_invoice',
    'SELECT "Constraint does not exist, skipping drop" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create the unique constraint
ALTER TABLE invoices 
ADD UNIQUE KEY unique_xero_invoice (xero_customer_number, invoice_date);

-- Add index for reconciliation queries
CREATE INDEX IF NOT EXISTS idx_reconciliation_status ON invoices(reconciliation_status);
CREATE INDEX IF NOT EXISTS idx_xero_customer ON invoices(xero_customer_number);

-- ============================================================================
-- STEP 3: Create invoice_camera_allocations table
-- ============================================================================

CREATE TABLE IF NOT EXISTS invoice_camera_allocations (
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
    
    -- Prevent duplicate allocations for same invoice + store
    UNIQUE KEY unique_invoice_store (invoice_id, store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks which cameras from which stores are allocated to each invoice';

-- ============================================================================
-- STEP 4: Add helper views for reconciliation
-- ============================================================================

-- View: Invoice reconciliation summary
CREATE OR REPLACE VIEW v_invoice_reconciliation AS
SELECT 
    i.id,
    i.invoice_number,
    i.invoice_date,
    i.xero_customer_number,
    le.legal_entity_name,
    i.invoice_amount,
    i.expected_amount,
    i.variance,
    i.reconciliation_status,
    i.payment_status,
    COUNT(DISTINCT ica.store_id) as stores_count,
    SUM(ica.main_cameras) as total_main_cameras,
    SUM(ica.additional_cameras) as total_additional_cameras,
    SUM(ica.subtotal) as calculated_total
FROM invoices i
LEFT JOIN legal_entities le ON i.legal_entity_id = le.id
LEFT JOIN invoice_camera_allocations ica ON i.id = ica.invoice_id
GROUP BY i.id;

-- ============================================================================
-- DONE
-- ============================================================================

SELECT 'Invoice Reconciliation Redesign Migration Complete!' AS status;

