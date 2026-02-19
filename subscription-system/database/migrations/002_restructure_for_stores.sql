-- Migration 002: Restructure for Store-Level Tracking
-- This migration restructures the database to support:
-- - Legal Entities (top level)
-- - Stores (belong to Legal Entities)
-- - Camera Installations (detailed tracking at store level)
-- - Invoice validation against camera counts

-- ============================================================================
-- STEP 1: Update legal_entities table structure
-- ============================================================================

-- Ensure legal_entity_name has data (copy from subscriber_name if needed)
UPDATE legal_entities
SET legal_entity_name = subscriber_name
WHERE legal_entity_name IS NULL OR legal_entity_name = '';

-- Drop subscriber_name column (now redundant)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
               AND TABLE_NAME = 'legal_entities'
               AND COLUMN_NAME = 'subscriber_name');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE legal_entities DROP COLUMN subscriber_name', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Add legal_entity_id column
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
               AND TABLE_NAME = 'legal_entities'
               AND COLUMN_NAME = 'legal_entity_id');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE legal_entities ADD COLUMN legal_entity_id VARCHAR(50) UNIQUE AFTER id', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Ensure legal_entity_name is NOT NULL
ALTER TABLE legal_entities
    MODIFY COLUMN legal_entity_name VARCHAR(255) NOT NULL;

-- ============================================================================
-- STEP 2: Create stores table
-- ============================================================================

CREATE TABLE stores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    legal_entity_id INT NOT NULL,
    store_id VARCHAR(50) UNIQUE NOT NULL,
    store_name VARCHAR(255) NOT NULL,
    store_code VARCHAR(50),
    installation_date DATE,
    termination_date DATE,
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    postcode VARCHAR(20),
    category VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE,
    INDEX idx_store_id (store_id),
    INDEX idx_store_name (store_name),
    INDEX idx_legal_entity (legal_entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- STEP 3: Create detailed camera_installations table
-- ============================================================================

CREATE TABLE camera_installations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    store_id INT NOT NULL,
    installation_date DATE NOT NULL,
    removal_date DATE NULL,
    camera_type ENUM('main', 'additional') NOT NULL,
    invoice_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    INDEX idx_store (store_id),
    INDEX idx_installation_date (installation_date),
    INDEX idx_invoice (invoice_id),
    INDEX idx_active (removal_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- STEP 4: Rename camera_counts to camera_counts_monthly (cumulative tracking)
-- ============================================================================

RENAME TABLE camera_counts TO camera_counts_monthly;

-- Add store_id column and update foreign key
ALTER TABLE camera_counts_monthly
    ADD COLUMN store_id INT AFTER id,
    ADD COLUMN validation_status ENUM('pending', 'matched', 'mismatch') DEFAULT 'pending',
    ADD COLUMN discrepancy_notes TEXT;

-- We'll populate store_id in the data migration step

-- ============================================================================
-- STEP 5: Update invoices table
-- ============================================================================

ALTER TABLE invoices
    CHANGE COLUMN subscriber_id legal_entity_id INT NOT NULL,
    ADD COLUMN expected_amount DECIMAL(10,2) NULL COMMENT 'Calculated expected amount',
    ADD COLUMN validation_status ENUM('pending', 'matched', 'mismatch', 'manual_override') DEFAULT 'pending',
    ADD COLUMN validation_notes TEXT;

-- Update foreign key
ALTER TABLE invoices
    DROP FOREIGN KEY invoices_ibfk_1;
    
ALTER TABLE invoices
    ADD CONSTRAINT fk_invoice_legal_entity 
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE;

-- ============================================================================
-- STEP 6: Update subscriber_contracts to legal_entity_contracts
-- ============================================================================

RENAME TABLE subscriber_contracts TO legal_entity_contracts;

ALTER TABLE legal_entity_contracts
    CHANGE COLUMN subscriber_id legal_entity_id INT NOT NULL;

-- Update foreign key
ALTER TABLE legal_entity_contracts
    DROP FOREIGN KEY subscriber_contracts_ibfk_1;
    
ALTER TABLE legal_entity_contracts
    ADD CONSTRAINT fk_contract_legal_entity 
    FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE;

-- ============================================================================
-- STEP 7: Create new import tracking tables
-- ============================================================================

CREATE TABLE store_imports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    filename VARCHAR(255),
    rows_imported INT DEFAULT 0,
    rows_updated INT DEFAULT 0,
    status ENUM('success', 'partial', 'failed') DEFAULT 'success',
    error_log TEXT,
    imported_by INT,
    
    FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_import_date (import_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE camera_installation_imports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    filename VARCHAR(255),
    import_type ENUM('incremental', 'cumulative') NOT NULL,
    rows_imported INT DEFAULT 0,
    rows_failed INT DEFAULT 0,
    status ENUM('success', 'partial', 'failed') DEFAULT 'success',
    error_log TEXT,
    imported_by INT,
    
    FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_import_date (import_date),
    INDEX idx_import_type (import_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

