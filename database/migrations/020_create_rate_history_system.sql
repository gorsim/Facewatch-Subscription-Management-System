-- Migration 020: Create Rate History and Discount System
-- Date: 2026-02-15
-- Purpose: Implement versioned camera rates with inflation adjustments and volume discounts

USE facewatch_subscriptions;

-- ============================================================================
-- STEP 1: Create rate history table
-- ============================================================================

CREATE TABLE IF NOT EXISTS legal_entity_rate_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    legal_entity_id INT NOT NULL,
    effective_date DATE NOT NULL COMMENT 'Date this rate becomes effective',
    end_date DATE NULL COMMENT 'Date this rate stops being effective (NULL = current)',
    main_camera_rate DECIMAL(10,2) NOT NULL COMMENT 'Rate per main camera',
    additional_camera_rate DECIMAL(10,2) NOT NULL COMMENT 'Rate per additional camera',
    total_cameras INT NOT NULL DEFAULT 0 COMMENT 'Total camera count at time of rate change',
    discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Volume discount applied',
    base_main_rate DECIMAL(10,2) NOT NULL COMMENT 'Base rate before discount',
    base_additional_rate DECIMAL(10,2) NOT NULL COMMENT 'Base rate before discount',
    change_reason ENUM('initial', 'inflation', 'volume_discount', 'manual', 'contract_change') NOT NULL DEFAULT 'manual',
    inflation_rate DECIMAL(5,2) NULL COMMENT 'Inflation percentage applied (if applicable)',
    notes TEXT NULL,
    created_by VARCHAR(100) NULL COMMENT 'User or system that created this rate',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE,
    INDEX idx_legal_entity_date (legal_entity_id, effective_date),
    INDEX idx_effective_date (effective_date),
    INDEX idx_current_rates (legal_entity_id, end_date),
    UNIQUE KEY unique_entity_date (legal_entity_id, effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Historical camera rates for legal entities';

-- ============================================================================
-- STEP 2: Create volume discount tiers table
-- ============================================================================

CREATE TABLE IF NOT EXISTS volume_discount_tiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    min_cameras INT NOT NULL COMMENT 'Minimum camera count for this tier',
    max_cameras INT NULL COMMENT 'Maximum camera count (NULL = unlimited)',
    discount_percentage DECIMAL(5,2) NOT NULL COMMENT 'Discount percentage to apply',
    tier_name VARCHAR(50) NOT NULL COMMENT 'Name of this tier (e.g., "Standard", "Bronze", "Silver")',
    effective_date DATE NOT NULL DEFAULT '2024-01-01',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_camera_range (min_cameras, max_cameras),
    INDEX idx_active (is_active, effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Volume discount tiers based on camera count';

-- ============================================================================
-- STEP 3: Create base rates table (standard pricing)
-- ============================================================================

CREATE TABLE IF NOT EXISTS base_camera_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    effective_date DATE NOT NULL COMMENT 'Date this base rate becomes effective',
    main_camera_rate DECIMAL(10,2) NOT NULL COMMENT 'Base rate per main camera (before discounts)',
    additional_camera_rate DECIMAL(10,2) NOT NULL COMMENT 'Base rate per additional camera (before discounts)',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Base camera rates (standard pricing before discounts)';

-- ============================================================================
-- STEP 4: Create inflation adjustment log
-- ============================================================================

CREATE TABLE IF NOT EXISTS inflation_adjustments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    adjustment_date DATE NOT NULL COMMENT 'Date inflation was applied',
    inflation_rate DECIMAL(5,2) NOT NULL COMMENT 'Inflation percentage applied',
    previous_main_rate DECIMAL(10,2) NOT NULL,
    new_main_rate DECIMAL(10,2) NOT NULL,
    previous_additional_rate DECIMAL(10,2) NOT NULL,
    new_additional_rate DECIMAL(10,2) NOT NULL,
    entities_affected INT NOT NULL DEFAULT 0 COMMENT 'Number of legal entities updated',
    applied_by VARCHAR(100) NULL COMMENT 'User or system that applied inflation',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_adjustment_date (adjustment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Log of inflation adjustments applied';

-- ============================================================================
-- STEP 5: Insert default volume discount tiers
-- ============================================================================

INSERT INTO volume_discount_tiers (min_cameras, max_cameras, discount_percentage, tier_name, effective_date) VALUES
(0, 99, 0.00, 'Standard', '2024-01-01'),
(100, 199, 5.00, 'Bronze', '2024-01-01'),
(200, 499, 10.00, 'Silver', '2024-01-01'),
(500, 999, 22.00, 'Gold', '2024-01-01'),
(1000, NULL, 30.00, 'Platinum', '2024-01-01')
ON DUPLICATE KEY UPDATE discount_percentage = VALUES(discount_percentage);

-- ============================================================================
-- STEP 6: Insert current base rates
-- ============================================================================

INSERT INTO base_camera_rates (effective_date, main_camera_rate, additional_camera_rate, notes)
VALUES ('2024-01-01', 1642.00, 1095.00, 'Initial base rates from existing system')
ON DUPLICATE KEY UPDATE main_camera_rate = VALUES(main_camera_rate);

-- ============================================================================
-- STEP 7: Migrate existing rates to history
-- ============================================================================

-- Insert current rates from legal_entities into rate_history
INSERT INTO legal_entity_rate_history 
    (legal_entity_id, effective_date, end_date, main_camera_rate, additional_camera_rate, 
     total_cameras, discount_percentage, base_main_rate, base_additional_rate, 
     change_reason, created_by, notes)
SELECT 
    le.id,
    COALESCE(
        (SELECT MIN(invoice_date) FROM invoices WHERE legal_entity_id = le.id),
        '2024-01-01'
    ) as effective_date,
    NULL as end_date,
    le.main_camera_rate,
    le.additional_camera_rate,
    (SELECT COUNT(*) 
     FROM camera_installations ci 
     JOIN stores s ON ci.store_id = s.id 
     WHERE s.legal_entity_id = le.id 
     AND ci.removal_date IS NULL) as total_cameras,
    0.00 as discount_percentage,
    le.main_camera_rate as base_main_rate,
    le.additional_camera_rate as base_additional_rate,
    'initial' as change_reason,
    'system_migration' as created_by,
    'Migrated from legal_entities table' as notes
FROM legal_entities le
WHERE le.main_camera_rate > 0
ON DUPLICATE KEY UPDATE 
    main_camera_rate = VALUES(main_camera_rate),
    additional_camera_rate = VALUES(additional_camera_rate);


