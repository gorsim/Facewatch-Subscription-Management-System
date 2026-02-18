-- Migration 011: Rename xero_customer_number to xero_company_name
-- Date: 2026-02-15
-- Purpose: Rename column to better reflect that it stores company names, not numbers

USE facewatch_subscriptions;

-- ============================================================================
-- Rename column in legal_entities table
-- ============================================================================

-- Check if xero_customer_number exists
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' 
    AND COLUMN_NAME = 'xero_customer_number'
);

-- Rename the column if it exists
SET @sql = IF(@column_exists > 0,
    'ALTER TABLE legal_entities CHANGE COLUMN xero_customer_number xero_company_name VARCHAR(255) NULL',
    'SELECT "Column xero_customer_number does not exist in legal_entities" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop old index and create new one
DROP INDEX IF EXISTS idx_xero_customer_number ON legal_entities;
CREATE INDEX IF NOT EXISTS idx_xero_company_name ON legal_entities(xero_company_name);

-- ============================================================================
-- Rename column in invoices table
-- ============================================================================

-- Check if xero_customer_number exists in invoices
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' 
    AND COLUMN_NAME = 'xero_customer_number'
);

-- Rename the column if it exists
SET @sql = IF(@column_exists > 0,
    'ALTER TABLE invoices CHANGE COLUMN xero_customer_number xero_company_name VARCHAR(255) NULL',
    'SELECT "Column xero_customer_number does not exist in invoices" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop old index and create new one
DROP INDEX IF EXISTS idx_xero_customer ON invoices;
CREATE INDEX IF NOT EXISTS idx_xero_company ON invoices(xero_company_name);

-- Update unique constraint
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
    AND TABLE_NAME = 'invoices'
    AND CONSTRAINT_NAME = 'unique_xero_invoice'
);

SET @sql = IF(@constraint_exists > 0,
    'ALTER TABLE invoices DROP INDEX unique_xero_invoice',
    'SELECT "Constraint unique_xero_invoice does not exist" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recreate unique constraint with new column name
ALTER TABLE invoices 
ADD UNIQUE KEY unique_xero_invoice (xero_company_name, invoice_date);

-- ============================================================================
-- Verification
-- ============================================================================

-- Show legal entities with new column name
SELECT 
    legal_entity_id,
    legal_entity_name,
    xero_company_name,
    main_camera_rate,
    additional_camera_rate
FROM legal_entities
LIMIT 5;

-- Show invoices with new column name
SELECT 
    invoice_number,
    xero_company_name,
    invoice_date,
    invoice_amount
FROM invoices
LIMIT 5;

-- ============================================================================
-- Success message
-- ============================================================================

SELECT '✅ Successfully renamed xero_customer_number to xero_company_name in both tables!' AS status;

