-- Migration 012: Rename xero_customer_number to xero_company_name (Fixed)
-- Date: 2026-02-15
-- Purpose: Rename column to better reflect that it stores company names, not numbers
-- Fixed: Compatible with older MySQL versions

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

-- Drop old index (check if exists first)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' 
    AND INDEX_NAME = 'idx_xero_customer_number'
);

SET @sql = IF(@index_exists > 0,
    'ALTER TABLE legal_entities DROP INDEX idx_xero_customer_number',
    'SELECT "Index idx_xero_customer_number does not exist" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create new index
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' 
    AND INDEX_NAME = 'idx_xero_company_name'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_xero_company_name ON legal_entities(xero_company_name)',
    'SELECT "Index idx_xero_company_name already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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

-- Drop old index (check if exists first)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' 
    AND INDEX_NAME = 'idx_xero_customer'
);

SET @sql = IF(@index_exists > 0,
    'ALTER TABLE invoices DROP INDEX idx_xero_customer',
    'SELECT "Index idx_xero_customer does not exist" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create new index
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' 
    AND INDEX_NAME = 'idx_xero_company'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_xero_company ON invoices(xero_company_name)',
    'SELECT "Index idx_xero_company already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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

-- Recreate unique constraint with new column name (only if column was renamed)
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' 
    AND COLUMN_NAME = 'xero_company_name'
);

SET @sql = IF(@column_exists > 0,
    'ALTER TABLE invoices ADD UNIQUE KEY unique_xero_invoice (xero_company_name, invoice_date)',
    'SELECT "Cannot create constraint - xero_company_name column does not exist" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Success message
-- ============================================================================

SELECT '✅ Successfully renamed xero_customer_number to xero_company_name!' AS status;

