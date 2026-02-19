-- Migration 014: Add Payment Frequency to Legal Entities
-- Date: 2026-02-15
-- Purpose: Add payment_frequency column to track billing frequency

USE facewatch_subscriptions;

-- ============================================================================
-- Add payment_frequency column to legal_entities
-- ============================================================================

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' 
    AND COLUMN_NAME = 'payment_frequency'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE legal_entities ADD COLUMN payment_frequency ENUM(''annual'', ''quarterly'', ''monthly'') DEFAULT ''annual'' COMMENT ''Billing frequency'' AFTER additional_camera_rate',
    'SELECT "Column payment_frequency already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Verification
-- ============================================================================

SELECT 
    legal_entity_id,
    legal_entity_name,
    xero_company_name,
    main_camera_rate,
    additional_camera_rate,
    payment_frequency
FROM legal_entities
LIMIT 5;

-- ============================================================================
-- Success message
-- ============================================================================

SELECT '✅ Successfully added payment_frequency column to legal_entities!' AS status;

