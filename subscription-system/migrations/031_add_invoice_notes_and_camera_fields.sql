-- Migration 031: Add Invoice Notes and Camera Fields
-- Date: 2026-02-18
-- Purpose: Add notes field to invoices and camera_name/safr_code to camera_installations

USE facewatch_subscriptions;

-- ============================================================================
-- STEP 1: Add notes column to invoices table (if not exists)
-- ============================================================================

-- Check if notes column already exists
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'invoices' 
    AND COLUMN_NAME = 'notes'
);

-- Add notes column if it doesn't exist
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE invoices ADD COLUMN notes TEXT NULL COMMENT "Internal notes about this invoice"',
    'SELECT "Column notes already exists in invoices table" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 2: Add camera_name column to camera_installations table
-- ============================================================================

-- Check if camera_name column already exists
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'camera_installations' 
    AND COLUMN_NAME = 'camera_name'
);

-- Add camera_name column if it doesn't exist
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE camera_installations ADD COLUMN camera_name VARCHAR(255) NULL COMMENT "Friendly name for the camera" AFTER camera_type',
    'SELECT "Column camera_name already exists in camera_installations table" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 3: Add safr_code column to camera_installations table
-- ============================================================================

-- Check if safr_code column already exists
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'camera_installations' 
    AND COLUMN_NAME = 'safr_code'
);

-- Add safr_code column if it doesn't exist
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE camera_installations ADD COLUMN safr_code VARCHAR(100) NULL COMMENT "SAFR system code for the camera" AFTER camera_name',
    'SELECT "Column safr_code already exists in camera_installations table" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 4: Add index on safr_code for faster lookups
-- ============================================================================

-- Check if index already exists
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'camera_installations' 
    AND INDEX_NAME = 'idx_safr_code'
);

-- Add index if it doesn't exist
SET @sql = IF(@index_exists = 0,
    'ALTER TABLE camera_installations ADD INDEX idx_safr_code (safr_code)',
    'SELECT "Index idx_safr_code already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migration complete
SELECT 'Migration 031 completed successfully - Added notes to invoices, camera_name and safr_code to camera_installations' AS status;

