-- Migration 028: Update Invoice Status Enum
-- Date: 2026-02-16
-- Purpose: Replace 'paid' status with 'reconciled_to_xero' since payment management happens in Xero

USE facewatch_subscriptions;

-- Update the invoice_status enum to use 'reconciled_to_xero' instead of 'paid'
ALTER TABLE invoices 
MODIFY COLUMN invoice_status ENUM('draft', 'issued', 'reconciled_to_xero', 'cancelled', 'merged') 
DEFAULT 'draft' 
COMMENT 'Invoice lifecycle status - reconciled_to_xero means matched with Xero invoice';

-- Add status change tracking table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add reconciliation tracking columns to invoices table
ALTER TABLE invoices
ADD COLUMN IF NOT EXISTS xero_invoice_id VARCHAR(100) NULL COMMENT 'Xero invoice ID for reconciliation',
ADD COLUMN IF NOT EXISTS xero_invoice_number VARCHAR(50) NULL COMMENT 'Invoice number in Xero',
ADD COLUMN IF NOT EXISTS reconciled_date DATE NULL COMMENT 'Date reconciled to Xero',
ADD COLUMN IF NOT EXISTS reconciled_by VARCHAR(100) NULL COMMENT 'User who reconciled',
ADD INDEX IF NOT EXISTS idx_xero_invoice_id (xero_invoice_id),
ADD INDEX IF NOT EXISTS idx_xero_invoice_number (xero_invoice_number);

-- Migration complete
SELECT 'Migration 028 completed successfully' AS status;

