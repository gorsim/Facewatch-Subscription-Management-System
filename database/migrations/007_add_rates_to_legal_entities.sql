-- Migration 007: Add Camera Rates to Legal Entities
-- Date: 2026-02-15
-- Purpose: Add main_camera_rate and additional_camera_rate columns to legal_entities table
--          These are needed for invoice reconciliation and camera allocation

USE facewatch_subscriptions;

-- ============================================================================
-- Add camera rate columns to legal_entities table
-- ============================================================================

-- Add main_camera_rate column
ALTER TABLE legal_entities 
ADD COLUMN IF NOT EXISTS main_camera_rate DECIMAL(10,2) NULL DEFAULT 0.00 
COMMENT 'Annual rate per main camera' 
AFTER xero_customer_number;

-- Add additional_camera_rate column
ALTER TABLE legal_entities 
ADD COLUMN IF NOT EXISTS additional_camera_rate DECIMAL(10,2) NULL DEFAULT 0.00 
COMMENT 'Annual rate per additional camera' 
AFTER main_camera_rate;

-- ============================================================================
-- Populate rates from subscriber_contracts (if they exist)
-- ============================================================================

-- Update legal_entities with the most recent contract rates
UPDATE legal_entities le
LEFT JOIN (
    SELECT 
        s.id as subscriber_id,
        sc.main_camera_rate,
        sc.additional_camera_rate
    FROM subscribers s
    JOIN subscriber_contracts sc ON s.id = sc.subscriber_id
    WHERE sc.effective_date = (
        SELECT MAX(effective_date) 
        FROM subscriber_contracts 
        WHERE subscriber_id = s.id
    )
) latest_contract ON le.id = latest_contract.subscriber_id
SET 
    le.main_camera_rate = COALESCE(latest_contract.main_camera_rate, 0.00),
    le.additional_camera_rate = COALESCE(latest_contract.additional_camera_rate, 0.00)
WHERE le.main_camera_rate IS NULL OR le.main_camera_rate = 0;

-- ============================================================================
-- Add indexes for performance
-- ============================================================================

CREATE INDEX IF NOT EXISTS idx_main_camera_rate ON legal_entities(main_camera_rate);
CREATE INDEX IF NOT EXISTS idx_additional_camera_rate ON legal_entities(additional_camera_rate);

-- ============================================================================
-- Verification
-- ============================================================================

-- Show legal entities with their rates
SELECT 
    legal_entity_id,
    legal_entity_name,
    xero_customer_number,
    main_camera_rate,
    additional_camera_rate
FROM legal_entities
ORDER BY legal_entity_name
LIMIT 10;

-- Show count of legal entities with rates set
SELECT 
    COUNT(*) as total_entities,
    SUM(CASE WHEN main_camera_rate > 0 THEN 1 ELSE 0 END) as entities_with_main_rate,
    SUM(CASE WHEN additional_camera_rate > 0 THEN 1 ELSE 0 END) as entities_with_additional_rate
FROM legal_entities;

