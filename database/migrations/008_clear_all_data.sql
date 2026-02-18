-- Migration 008: Clear All Data from Tables
-- Date: 2026-02-15
-- Purpose: Delete all data from all tables to start fresh with new uploads
-- WARNING: This will delete ALL data but preserve the table structure

USE facewatch_subscriptions;

-- Disable foreign key checks to allow deletion in any order
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- Clear all data from tables (in reverse dependency order for safety)
-- ============================================================================

-- Clear invoice-related data first (in correct dependency order)
TRUNCATE TABLE invoice_camera_allocations;

-- Clear prepayment data BEFORE invoices (prepayments references invoices)
TRUNCATE TABLE prepayments;

-- Now safe to clear invoices
TRUNCATE TABLE invoices;

-- Clear camera installation data
TRUNCATE TABLE camera_installations;

-- Clear camera counts
TRUNCATE TABLE camera_counts_monthly;

-- Clear contract data
TRUNCATE TABLE subscriber_contracts;

-- Clear store data
TRUNCATE TABLE stores;

-- Clear legal entity data
TRUNCATE TABLE legal_entities;

-- Clear subscriber data (if it still exists as separate table)
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'subscribers'
);

SET @sql = IF(@table_exists > 0,
    'TRUNCATE TABLE subscribers',
    'SELECT "Table subscribers does not exist, skipping" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Clear pricing and inflation data
TRUNCATE TABLE pricing_tiers;
TRUNCATE TABLE inflation_rates;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Verification - Show row counts for all tables
-- ============================================================================

SELECT 'legal_entities' as table_name, COUNT(*) as row_count FROM legal_entities
UNION ALL
SELECT 'stores', COUNT(*) FROM stores
UNION ALL
SELECT 'camera_installations', COUNT(*) FROM camera_installations
UNION ALL
SELECT 'camera_counts_monthly', COUNT(*) FROM camera_counts_monthly
UNION ALL
SELECT 'invoices', COUNT(*) FROM invoices
UNION ALL
SELECT 'invoice_camera_allocations', COUNT(*) FROM invoice_camera_allocations
UNION ALL
SELECT 'subscriber_contracts', COUNT(*) FROM subscriber_contracts
UNION ALL
SELECT 'prepayments', COUNT(*) FROM prepayments
UNION ALL
SELECT 'pricing_tiers', COUNT(*) FROM pricing_tiers
UNION ALL
SELECT 'inflation_rates', COUNT(*) FROM inflation_rates;

-- ============================================================================
-- Success message
-- ============================================================================

SELECT 'All data cleared successfully! Tables are empty and ready for fresh data upload.' AS status;

