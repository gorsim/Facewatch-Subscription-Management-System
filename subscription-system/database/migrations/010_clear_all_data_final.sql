-- Migration 010: Clear All Data (Final Safe Version)
-- Date: 2026-02-15
-- Purpose: Delete all data from all tables that exist
-- Checks for table existence before attempting to delete

USE facewatch_subscriptions;

-- ============================================================================
-- Delete all data from tables (only if they exist)
-- ============================================================================

-- Clear invoice-related data first
DELETE FROM invoice_camera_allocations WHERE 1=1;
DELETE FROM prepayments WHERE 1=1;
DELETE FROM invoices WHERE 1=1;

-- Clear camera installation data
DELETE FROM camera_installations WHERE 1=1;

-- Clear camera counts
DELETE FROM camera_counts_monthly WHERE 1=1;

-- Clear store data
DELETE FROM stores WHERE 1=1;

-- Clear legal entity data
DELETE FROM legal_entities WHERE 1=1;

-- Clear pricing and inflation data (if they exist)
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' AND TABLE_NAME = 'pricing_tiers');
SET @sql = IF(@table_exists > 0, 'DELETE FROM pricing_tiers WHERE 1=1', 'SELECT "pricing_tiers does not exist" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' AND TABLE_NAME = 'inflation_rates');
SET @sql = IF(@table_exists > 0, 'DELETE FROM inflation_rates WHERE 1=1', 'SELECT "inflation_rates does not exist" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' AND TABLE_NAME = 'subscriber_contracts');
SET @sql = IF(@table_exists > 0, 'DELETE FROM subscriber_contracts WHERE 1=1', 'SELECT "subscriber_contracts does not exist" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' AND TABLE_NAME = 'subscribers');
SET @sql = IF(@table_exists > 0, 'DELETE FROM subscribers WHERE 1=1', 'SELECT "subscribers does not exist" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Reset auto-increment counters for tables that exist
ALTER TABLE legal_entities AUTO_INCREMENT = 1;
ALTER TABLE stores AUTO_INCREMENT = 1;
ALTER TABLE camera_installations AUTO_INCREMENT = 1;
ALTER TABLE camera_counts_monthly AUTO_INCREMENT = 1;
ALTER TABLE invoices AUTO_INCREMENT = 1;
ALTER TABLE invoice_camera_allocations AUTO_INCREMENT = 1;
ALTER TABLE prepayments AUTO_INCREMENT = 1;

-- ============================================================================
-- Verification - Show row counts for main tables
-- ============================================================================

SELECT 'legal_entities' as table_name, COUNT(*) as row_count FROM legal_entities
UNION ALL SELECT 'stores', COUNT(*) FROM stores
UNION ALL SELECT 'camera_installations', COUNT(*) FROM camera_installations
UNION ALL SELECT 'camera_counts_monthly', COUNT(*) FROM camera_counts_monthly
UNION ALL SELECT 'invoices', COUNT(*) FROM invoices
UNION ALL SELECT 'invoice_camera_allocations', COUNT(*) FROM invoice_camera_allocations
UNION ALL SELECT 'prepayments', COUNT(*) FROM prepayments;

-- ============================================================================
-- Success message
-- ============================================================================

SELECT '✅ All data cleared successfully! Tables are empty and ready for fresh data upload.' AS status;

