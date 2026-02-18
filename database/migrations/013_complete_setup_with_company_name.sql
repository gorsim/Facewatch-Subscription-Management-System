-- Migration 013: Complete Setup with Xero Company Name
-- Date: 2026-02-15
-- Purpose: Add all necessary columns including xero_company_name and rates
-- This migration assumes tables exist but may be missing columns

USE facewatch_subscriptions;

-- ============================================================================
-- STEP 1: Add xero_company_name to legal_entities (if it doesn't exist)
-- ============================================================================

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' 
    AND COLUMN_NAME = 'xero_company_name'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE legal_entities ADD COLUMN xero_company_name VARCHAR(255) NULL AFTER legal_entity_name',
    'SELECT "Column xero_company_name already exists in legal_entities" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 2: Add camera rate columns to legal_entities (if they don't exist)
-- ============================================================================

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' 
    AND COLUMN_NAME = 'main_camera_rate'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE legal_entities ADD COLUMN main_camera_rate DECIMAL(10,2) NULL DEFAULT 0.00 COMMENT "Annual rate per main camera" AFTER xero_company_name',
    'SELECT "Column main_camera_rate already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' 
    AND COLUMN_NAME = 'additional_camera_rate'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE legal_entities ADD COLUMN additional_camera_rate DECIMAL(10,2) NULL DEFAULT 0.00 COMMENT "Annual rate per additional camera" AFTER main_camera_rate',
    'SELECT "Column additional_camera_rate already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 3: Add columns to invoices table
-- ============================================================================

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' 
    AND COLUMN_NAME = 'xero_company_name'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE invoices ADD COLUMN xero_company_name VARCHAR(255) NULL AFTER legal_entity_id',
    'SELECT "Column xero_company_name already exists in invoices" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' 
    AND COLUMN_NAME = 'expected_amount'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE invoices ADD COLUMN expected_amount DECIMAL(10,2) NULL COMMENT "Expected invoice amount based on camera counts"',
    'SELECT "Column expected_amount already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' 
    AND COLUMN_NAME = 'variance'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE invoices ADD COLUMN variance DECIMAL(10,2) NULL COMMENT "Difference between invoice_amount and expected_amount"',
    'SELECT "Column variance already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' 
    AND COLUMN_NAME = 'reconciliation_status'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE invoices ADD COLUMN reconciliation_status ENUM("matched", "under_charged", "over_charged", "pending") DEFAULT "pending" COMMENT "Reconciliation status based on variance"',
    'SELECT "Column reconciliation_status already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 4: Create indexes
-- ============================================================================

-- Index on xero_company_name in legal_entities
SET @index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' AND INDEX_NAME = 'idx_xero_company_name'
);
SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_xero_company_name ON legal_entities(xero_company_name)',
    'SELECT "Index idx_xero_company_name already exists" AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index on xero_company_name in invoices
SET @index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' AND INDEX_NAME = 'idx_xero_company'
);
SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_xero_company ON invoices(xero_company_name)',
    'SELECT "Index idx_xero_company already exists" AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index on reconciliation_status
SET @index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' AND INDEX_NAME = 'idx_reconciliation_status'
);
SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_reconciliation_status ON invoices(reconciliation_status)',
    'SELECT "Index idx_reconciliation_status already exists" AS msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Success message
-- ============================================================================

SELECT '✅ Database setup complete! All columns and indexes added.' AS status;

-- Show the structure
SELECT 'legal_entities columns:' AS info;
SHOW COLUMNS FROM legal_entities;

SELECT 'invoices columns:' AS info;
SHOW COLUMNS FROM invoices;

