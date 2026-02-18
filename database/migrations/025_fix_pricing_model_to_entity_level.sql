-- Migration 025: Fix Pricing Model - Move to Legal Entity Level
-- Date: 2026-02-16
-- Purpose: Pricing models should be at Legal Entity level, not store level
-- Two models: "volume_based" (default) and "first_plus_additional" (independent stores)

USE facewatch_subscriptions;

-- ============================================================================
-- STEP 1: Remove store-level pricing_model column (not needed)
-- ============================================================================

ALTER TABLE stores DROP COLUMN IF EXISTS pricing_model;

-- ============================================================================
-- STEP 2: Add pricing_model to legal_entities table
-- ============================================================================

ALTER TABLE legal_entities 
ADD COLUMN IF NOT EXISTS pricing_model ENUM('volume_based', 'first_plus_additional') 
DEFAULT 'volume_based' 
COMMENT 'Pricing model: volume_based (all cameras same rate by tier) or first_plus_additional (first camera full price, additional discounted)'
AFTER pricing_type;

-- ============================================================================
-- STEP 3: Rename tables to reflect entity-level pricing
-- ============================================================================

-- Rename default_store_pricing to default_independent_pricing
RENAME TABLE default_store_pricing TO default_independent_pricing;

-- Rename store_pricing to legal_entity_independent_pricing
RENAME TABLE store_pricing TO legal_entity_independent_pricing;

-- Update foreign key reference
ALTER TABLE legal_entity_independent_pricing 
DROP FOREIGN KEY IF EXISTS store_pricing_ibfk_1;

ALTER TABLE legal_entity_independent_pricing 
CHANGE COLUMN store_id legal_entity_id INT NOT NULL;

ALTER TABLE legal_entity_independent_pricing 
ADD CONSTRAINT fk_entity_independent_pricing 
FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE;

-- ============================================================================
-- STEP 4: Update comments to reflect entity-level pricing
-- ============================================================================

ALTER TABLE default_independent_pricing 
COMMENT = 'Default first camera + additional pricing for entities using independent pricing model';

ALTER TABLE legal_entity_independent_pricing 
COMMENT = 'Entity-specific first camera + additional pricing overrides';

-- ============================================================================
-- STEP 5: Verification
-- ============================================================================

SELECT 'Migration 025 completed successfully' AS status;

-- Show the new structure
SHOW COLUMNS FROM legal_entities LIKE 'pricing_model';
SHOW TABLES LIKE '%independent%';

SELECT 
    'All legal entities now default to volume_based pricing model' AS message,
    COUNT(*) as entity_count
FROM legal_entities;

