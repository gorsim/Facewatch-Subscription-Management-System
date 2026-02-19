-- Migration 021: Update Pricing Structure to Match Actual Business Model
-- Date: 2026-02-15
-- Purpose: Simplify pricing to match the actual volume-based pricing table

USE facewatch_subscriptions;

-- ============================================================================
-- STEP 1: Drop the complex discount system tables (we'll use a simpler model)
-- ============================================================================

DROP TABLE IF EXISTS inflation_adjustments;
DROP TABLE IF EXISTS base_camera_rates;
DROP TABLE IF EXISTS volume_discount_tiers;

-- ============================================================================
-- STEP 2: Create simplified camera_pricing table
-- ============================================================================

CREATE TABLE IF NOT EXISTS camera_pricing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    effective_date DATE NOT NULL COMMENT 'Date these prices become effective (e.g., 2024-01-01)',
    min_cameras INT NOT NULL COMMENT 'Minimum camera count for this tier',
    max_cameras INT NULL COMMENT 'Maximum camera count (NULL = unlimited)',
    price_per_annum DECIMAL(10,2) NOT NULL COMMENT 'Annual price per camera',
    price_per_quarter DECIMAL(10,2) NOT NULL COMMENT 'Quarterly price per camera',
    price_per_month DECIMAL(10,2) NOT NULL COMMENT 'Monthly price per camera',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_effective_date (effective_date),
    INDEX idx_camera_range (min_cameras, max_cameras),
    UNIQUE KEY unique_date_range (effective_date, min_cameras)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Volume-based camera pricing tiers';

-- ============================================================================
-- STEP 3: Insert current pricing (as of 01-Jan-24)
-- ============================================================================

INSERT INTO camera_pricing (effective_date, min_cameras, max_cameras, price_per_annum, price_per_quarter, price_per_month, notes) VALUES
('2024-01-01', 1, 49, 3540.00, 885.00, 295.00, 'Tier 1: 1-49 cameras'),
('2024-01-01', 50, 149, 3417.00, 854.25, 285.00, 'Tier 2: 50-149 cameras'),
('2024-01-01', 150, 249, 3298.00, 824.50, 275.00, 'Tier 3: 150-249 cameras'),
('2024-01-01', 250, 349, 3178.00, 794.50, 265.00, 'Tier 4: 250-349 cameras'),
('2024-01-01', 350, 499, 2997.00, 749.25, 250.00, 'Tier 5: 350-499 cameras'),
('2024-01-01', 500, NULL, 2759.00, 689.75, 230.00, 'Tier 6: 500+ cameras')
ON DUPLICATE KEY UPDATE
    price_per_annum = VALUES(price_per_annum),
    price_per_quarter = VALUES(price_per_quarter),
    price_per_month = VALUES(price_per_month);

-- ============================================================================
-- STEP 4: Update legal_entity_rate_history to use simpler structure
-- ============================================================================

-- Add columns for quarterly and monthly rates if they don't exist
ALTER TABLE legal_entity_rate_history 
ADD COLUMN IF NOT EXISTS price_per_annum DECIMAL(10,2) NULL COMMENT 'Annual price per camera' AFTER additional_camera_rate,
ADD COLUMN IF NOT EXISTS price_per_quarter DECIMAL(10,2) NULL COMMENT 'Quarterly price per camera' AFTER price_per_annum,
ADD COLUMN IF NOT EXISTS price_per_month DECIMAL(10,2) NULL COMMENT 'Monthly price per camera' AFTER price_per_quarter;

-- ============================================================================
-- STEP 5: Add payment_frequency to legal_entities if not exists
-- ============================================================================

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions' 
    AND TABLE_NAME = 'legal_entities' 
    AND COLUMN_NAME = 'payment_frequency'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE legal_entities ADD COLUMN payment_frequency ENUM("monthly", "quarterly", "annual") NOT NULL DEFAULT "annual" COMMENT "How often the customer pays" AFTER additional_camera_rate',
    'SELECT "Column payment_frequency already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 6: Verification
-- ============================================================================

SELECT 'Camera Pricing Table Created' as status;
SELECT * FROM camera_pricing ORDER BY effective_date DESC, min_cameras ASC;

