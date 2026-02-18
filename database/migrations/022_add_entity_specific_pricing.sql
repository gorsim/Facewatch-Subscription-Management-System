-- Migration 022: Add Entity-Specific Pricing
-- Date: 2026-02-15
-- Purpose: Allow legal entities to have custom pricing tables instead of using default pricing

USE facewatch_subscriptions;

-- ============================================================================
-- STEP 1: Add pricing_type to legal_entities
-- ============================================================================

-- Check if column exists, if not add it
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
    AND TABLE_NAME = 'legal_entities'
    AND COLUMN_NAME = 'pricing_type'
);

SET @sql = IF(@column_exists = 0,
    "ALTER TABLE legal_entities ADD COLUMN pricing_type ENUM('default', 'custom') NOT NULL DEFAULT 'default' COMMENT 'Whether to use default pricing or entity-specific pricing' AFTER payment_frequency",
    'SELECT "Column pricing_type already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 2: Create legal_entity_pricing table (entity-specific pricing tiers)
-- ============================================================================

CREATE TABLE IF NOT EXISTS legal_entity_pricing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    legal_entity_id INT NOT NULL,
    effective_date DATE NOT NULL COMMENT 'Date these prices become effective',
    min_cameras INT NOT NULL COMMENT 'Minimum camera count for this tier',
    max_cameras INT NULL COMMENT 'Maximum camera count (NULL = unlimited)',
    price_per_annum DECIMAL(10,2) NOT NULL COMMENT 'Annual price per camera',
    price_per_quarter DECIMAL(10,2) NOT NULL COMMENT 'Quarterly price per camera',
    price_per_month DECIMAL(10,2) NOT NULL COMMENT 'Monthly price per camera',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE,
    INDEX idx_legal_entity (legal_entity_id),
    INDEX idx_effective_date (effective_date),
    INDEX idx_camera_range (min_cameras, max_cameras),
    UNIQUE KEY unique_entity_date_range (legal_entity_id, effective_date, min_cameras)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Entity-specific camera pricing tiers';

-- ============================================================================
-- STEP 3: Migrate existing custom rates to entity-specific pricing
-- ============================================================================

-- Find legal entities with custom rates (different from standard pricing)
-- B&M has £2,700 main / £1,800 additional which doesn't match standard pricing

INSERT INTO legal_entity_pricing 
    (legal_entity_id, effective_date, min_cameras, max_cameras, 
     price_per_annum, price_per_quarter, price_per_month, notes)
SELECT 
    le.id,
    '2024-01-01' as effective_date,
    1 as min_cameras,
    NULL as max_cameras,
    le.main_camera_rate as price_per_annum,
    ROUND(le.main_camera_rate / 4, 2) as price_per_quarter,
    ROUND(le.main_camera_rate / 12, 2) as price_per_month,
    'Migrated from legacy main_camera_rate field' as notes
FROM legal_entities le
WHERE le.main_camera_rate > 0
AND le.main_camera_rate NOT IN (
    SELECT price_per_annum FROM camera_pricing WHERE effective_date = '2024-01-01'
)
ON DUPLICATE KEY UPDATE 
    price_per_annum = VALUES(price_per_annum),
    price_per_quarter = VALUES(price_per_quarter),
    price_per_month = VALUES(price_per_month);

-- Mark entities with custom pricing
UPDATE legal_entities le
SET pricing_type = 'custom'
WHERE EXISTS (
    SELECT 1 FROM legal_entity_pricing lep 
    WHERE lep.legal_entity_id = le.id
);

-- ============================================================================
-- STEP 4: Verification
-- ============================================================================

SELECT 'Entity-Specific Pricing Migration Complete' as status;

SELECT 
    le.legal_entity_name,
    le.pricing_type,
    COUNT(lep.id) as custom_pricing_tiers
FROM legal_entities le
LEFT JOIN legal_entity_pricing lep ON le.id = lep.legal_entity_id
GROUP BY le.id, le.legal_entity_name, le.pricing_type
ORDER BY le.legal_entity_name;

