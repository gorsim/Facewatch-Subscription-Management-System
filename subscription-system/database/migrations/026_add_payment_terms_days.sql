-- Migration 026: Add Payment Terms Days to Legal Entities
-- Date: 2026-02-16
-- Purpose: Add payment_terms_days column for cash flow forecasting

USE facewatch_subscriptions;

-- ============================================================================
-- Add payment_terms_days column to legal_entities
-- ============================================================================

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' 
    AND COLUMN_NAME = 'payment_terms_days'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE legal_entities ADD COLUMN payment_terms_days INT DEFAULT 30 COMMENT "Number of days until payment is expected" AFTER payment_frequency',
    'SELECT "Column payment_terms_days already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Migration complete
-- ============================================================================

SELECT 'Migration 026 complete: payment_terms_days column added to legal_entities' AS status;

