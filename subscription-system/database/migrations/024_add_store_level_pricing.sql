-- Migration 024: Add Store-Level Pricing for Smaller Stores
-- Date: 2026-02-16
-- Purpose: Add a second pricing model where first camera is full price and additional cameras are discounted
-- This is applied at the store level for smaller stores

USE facewatch_subscriptions;

-- ============================================================================
-- STEP 1: Create store_pricing table for historical pricing
-- ============================================================================

CREATE TABLE IF NOT EXISTS store_pricing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    store_id INT NOT NULL,
    effective_date DATE NOT NULL COMMENT 'Date these prices become effective',
    first_camera_price_per_annum DECIMAL(10,2) NOT NULL COMMENT 'Annual price for the first camera',
    first_camera_price_per_quarter DECIMAL(10,2) NOT NULL COMMENT 'Quarterly price for the first camera',
    first_camera_price_per_month DECIMAL(10,2) NOT NULL COMMENT 'Monthly price for the first camera',
    additional_camera_price_per_annum DECIMAL(10,2) NOT NULL COMMENT 'Annual price for cameras 2+',
    additional_camera_price_per_quarter DECIMAL(10,2) NOT NULL COMMENT 'Quarterly price for cameras 2+',
    additional_camera_price_per_month DECIMAL(10,2) NOT NULL COMMENT 'Monthly price for cameras 2+',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_store (store_id),
    INDEX idx_effective_date (effective_date),
    UNIQUE KEY unique_store_date (store_id, effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Store-specific first camera + additional pricing';

-- ============================================================================
-- STEP 2: Add pricing_model to stores table
-- ============================================================================

-- Check if column exists, if not add it
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
    AND TABLE_NAME = 'stores'
    AND COLUMN_NAME = 'pricing_model'
);

SET @sql = IF(@column_exists = 0,
    "ALTER TABLE stores ADD COLUMN pricing_model ENUM('default', 'first_plus_additional') NOT NULL DEFAULT 'default' COMMENT 'Pricing model: default (volume-based) or first_plus_additional (first camera full price, rest discounted)' AFTER store_id",
    'SELECT "Column pricing_model already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 3: Create default store pricing tiers (for reference)
-- ============================================================================

-- This table stores the default "first camera + additional" pricing over time
-- Stores using this model will reference these defaults unless they have custom pricing

CREATE TABLE IF NOT EXISTS default_store_pricing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    effective_date DATE NOT NULL COMMENT 'Date these prices become effective',
    first_camera_price_per_annum DECIMAL(10,2) NOT NULL COMMENT 'Default annual price for first camera',
    first_camera_price_per_quarter DECIMAL(10,2) NOT NULL COMMENT 'Default quarterly price for first camera',
    first_camera_price_per_month DECIMAL(10,2) NOT NULL COMMENT 'Default monthly price for first camera',
    additional_camera_price_per_annum DECIMAL(10,2) NOT NULL COMMENT 'Default annual price for cameras 2+',
    additional_camera_price_per_quarter DECIMAL(10,2) NOT NULL COMMENT 'Default quarterly price for cameras 2+',
    additional_camera_price_per_month DECIMAL(10,2) NOT NULL COMMENT 'Default monthly price for cameras 2+',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_effective_date (effective_date),
    UNIQUE KEY unique_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Default first camera + additional pricing for smaller stores';

-- ============================================================================
-- STEP 4: Insert initial default pricing (example values - adjust as needed)
-- ============================================================================

-- 2024 pricing
INSERT INTO default_store_pricing 
    (effective_date, first_camera_price_per_annum, first_camera_price_per_quarter, first_camera_price_per_month,
     additional_camera_price_per_annum, additional_camera_price_per_quarter, additional_camera_price_per_month, notes)
VALUES
    ('2024-01-01', 3540.00, 885.00, 295.00, 2800.00, 700.00, 233.33, 'Initial pricing for small stores - first camera full price, additional discounted')
ON DUPLICATE KEY UPDATE
    first_camera_price_per_annum = VALUES(first_camera_price_per_annum),
    additional_camera_price_per_annum = VALUES(additional_camera_price_per_annum);

-- 2025 pricing (4% inflation)
INSERT INTO default_store_pricing 
    (effective_date, first_camera_price_per_annum, first_camera_price_per_quarter, first_camera_price_per_month,
     additional_camera_price_per_annum, additional_camera_price_per_quarter, additional_camera_price_per_month, notes)
VALUES
    ('2025-01-01', 3680.00, 920.00, 307.00, 2912.00, 728.00, 242.67, '2025 pricing with 4% inflation')
ON DUPLICATE KEY UPDATE
    first_camera_price_per_annum = VALUES(first_camera_price_per_annum),
    additional_camera_price_per_annum = VALUES(additional_camera_price_per_annum);

-- 2026 pricing (3% inflation)
INSERT INTO default_store_pricing 
    (effective_date, first_camera_price_per_annum, first_camera_price_per_quarter, first_camera_price_per_month,
     additional_camera_price_per_annum, additional_camera_price_per_quarter, additional_camera_price_per_month, notes)
VALUES
    ('2026-01-01', 3790.00, 947.50, 316.00, 2999.00, 749.75, 250.00, '2026 pricing with 3% inflation')
ON DUPLICATE KEY UPDATE
    first_camera_price_per_annum = VALUES(first_camera_price_per_annum),
    additional_camera_price_per_annum = VALUES(additional_camera_price_per_annum);

-- ============================================================================
-- STEP 5: Verification
-- ============================================================================

SELECT 'Migration 024 completed successfully' AS status;
SELECT 'Store pricing tables created' AS message;
SELECT * FROM default_store_pricing ORDER BY effective_date DESC;

