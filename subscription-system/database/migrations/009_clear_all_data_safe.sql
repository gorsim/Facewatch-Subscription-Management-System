-- Migration 009: Clear All Data (Safe Version)
-- Date: 2026-02-15
-- Purpose: Delete all data from all tables using DELETE instead of TRUNCATE
-- This avoids foreign key constraint issues

USE facewatch_subscriptions;

-- ============================================================================
-- Delete all data from tables (using DELETE which respects foreign keys)
-- ============================================================================

-- Clear invoice-related data first
DELETE FROM invoice_camera_allocations;
DELETE FROM prepayments;
DELETE FROM invoices;

-- Clear camera installation data
DELETE FROM camera_installations;

-- Clear camera counts
DELETE FROM camera_counts_monthly;

-- Clear contract data
DELETE FROM subscriber_contracts;

-- Clear store data
DELETE FROM stores;

-- Clear legal entity data
DELETE FROM legal_entities;

-- Clear subscriber data (if it still exists as separate table)
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'subscribers'
);

SET @sql = IF(@table_exists > 0,
    'DELETE FROM subscribers',
    'SELECT "Table subscribers does not exist, skipping" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Clear pricing and inflation data
DELETE FROM pricing_tiers;
DELETE FROM inflation_rates;

-- Reset auto-increment counters to start from 1 again
ALTER TABLE legal_entities AUTO_INCREMENT = 1;
ALTER TABLE stores AUTO_INCREMENT = 1;
ALTER TABLE camera_installations AUTO_INCREMENT = 1;
ALTER TABLE camera_counts_monthly AUTO_INCREMENT = 1;
ALTER TABLE invoices AUTO_INCREMENT = 1;
ALTER TABLE invoice_camera_allocations AUTO_INCREMENT = 1;
ALTER TABLE subscriber_contracts AUTO_INCREMENT = 1;
ALTER TABLE prepayments AUTO_INCREMENT = 1;
ALTER TABLE pricing_tiers AUTO_INCREMENT = 1;
ALTER TABLE inflation_rates AUTO_INCREMENT = 1;

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

