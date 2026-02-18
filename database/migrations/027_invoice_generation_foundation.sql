-- Migration 027: Invoice Generation Foundation
-- Date: 2026-02-16
-- Purpose: Transform system to generate invoices instead of importing from Xero

USE facewatch_subscriptions;

-- ============================================================================
-- STEP 1: Add new columns to invoices table
-- ============================================================================

-- Add columns for invoice generation tracking
ALTER TABLE invoices
ADD COLUMN IF NOT EXISTS is_auto_generated BOOLEAN DEFAULT FALSE COMMENT 'Whether this invoice was auto-generated',
ADD COLUMN IF NOT EXISTS parent_invoice_id INT NULL COMMENT 'ID of the invoice this was generated from',
ADD COLUMN IF NOT EXISTS generation_date DATETIME NULL COMMENT 'When this invoice was generated',
ADD COLUMN IF NOT EXISTS next_generation_date DATE NULL COMMENT 'When the next invoice should be generated',
ADD COLUMN IF NOT EXISTS invoice_status ENUM('draft', 'issued', 'paid', 'cancelled', 'merged') DEFAULT 'draft' COMMENT 'Invoice lifecycle status',
ADD COLUMN IF NOT EXISTS merged_into_invoice_id INT NULL COMMENT 'If merged, the ID of the invoice it was merged into',
ADD COLUMN IF NOT EXISTS created_by VARCHAR(100) NULL COMMENT 'User who created this invoice',
ADD COLUMN IF NOT EXISTS notes TEXT NULL COMMENT 'Internal notes about this invoice';

-- Add index for parent invoice lookups
ALTER TABLE invoices
ADD INDEX IF NOT EXISTS idx_parent_invoice (parent_invoice_id),
ADD INDEX IF NOT EXISTS idx_next_generation (next_generation_date),
ADD INDEX IF NOT EXISTS idx_invoice_status (invoice_status);

-- ============================================================================
-- STEP 2: Create invoice_camera_allocations table
-- ============================================================================

CREATE TABLE IF NOT EXISTS invoice_camera_allocations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    camera_installation_id INT NOT NULL,
    store_id INT NOT NULL,
    legal_entity_id INT NOT NULL,
    
    -- Snapshot of camera details at time of allocation
    camera_serial VARCHAR(100),
    store_name VARCHAR(255),
    
    -- Allocation tracking
    allocated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_date DATETIME NULL,
    removed_reason VARCHAR(255) NULL,
    
    -- Pricing snapshot (what was charged for this camera on this invoice)
    price_charged DECIMAL(10,2) NOT NULL,
    pricing_tier VARCHAR(50) NULL COMMENT 'e.g., "first_camera", "additional", "tier_1-49"',
    
    -- Audit trail
    allocated_by VARCHAR(100) NULL,
    removed_by VARCHAR(100) NULL,
    notes TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (camera_installation_id) REFERENCES camera_installations(id),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id),
    
    INDEX idx_invoice (invoice_id),
    INDEX idx_camera (camera_installation_id),
    INDEX idx_store (store_id),
    INDEX idx_legal_entity (legal_entity_id),
    INDEX idx_allocated_date (allocated_date),
    INDEX idx_removed_date (removed_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- STEP 3: Create invoice_generation_log table
-- ============================================================================

CREATE TABLE IF NOT EXISTS invoice_generation_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    generation_type ENUM('manual', 'auto_repeat', 'merged', 'adjusted') NOT NULL,
    generation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- What triggered this generation
    triggered_by VARCHAR(100) NULL COMMENT 'User or system process',
    trigger_reason TEXT NULL,
    
    -- Generation details
    camera_count INT NULL,
    total_amount DECIMAL(10,2) NULL,
    pricing_model VARCHAR(50) NULL,
    
    -- Any issues during generation
    warnings TEXT NULL,
    errors TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice (invoice_id),
    INDEX idx_generation_date (generation_date),
    INDEX idx_generation_type (generation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Migration complete
-- ============================================================================

SELECT 'Migration 027 complete: Invoice generation foundation tables created' AS status;

