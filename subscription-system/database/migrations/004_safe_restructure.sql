-- Safe Restructure Migration
-- This script safely restructures the database, checking for existing objects

USE facewatch_subscriptions;

-- ============================================================================
-- STEP 1: Update legal_entities table
-- ============================================================================

-- Ensure legal_entity_name has data
UPDATE legal_entities
SET legal_entity_name = 'Unknown'
WHERE legal_entity_name IS NULL OR legal_entity_name = '';

-- Generate legal_entity_id for existing records
SET @counter = 0;
UPDATE legal_entities
SET legal_entity_id = CONCAT('LE', LPAD(@counter := @counter + 1, 4, '0'))
WHERE legal_entity_id IS NULL OR legal_entity_id = '';

-- ============================================================================
-- STEP 2: Create stores table if it doesn't exist
-- ============================================================================

CREATE TABLE IF NOT EXISTS stores (
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
-- STEP 3: Create camera_installations table if it doesn't exist
-- ============================================================================

CREATE TABLE IF NOT EXISTS camera_installations (
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
-- STEP 4: Create stores from existing legal entities (if stores table is empty)
-- ============================================================================

INSERT IGNORE INTO stores (
    legal_entity_id,
    store_id,
    store_name,
    installation_date,
    termination_date,
    category,
    notes,
    created_at,
    updated_at
)
SELECT 
    le.id,
    CONCAT(le.legal_entity_id, '-001') as store_id,
    CONCAT(le.legal_entity_name, ' - Main Store') as store_name,
    le.installation_date,
    le.termination_date,
    le.category,
    'Auto-created from legal entity' as notes,
    le.created_at,
    le.updated_at
FROM legal_entities le
WHERE NOT EXISTS (SELECT 1 FROM stores WHERE legal_entity_id = le.id);

-- ============================================================================
-- STEP 5: Create new import tracking tables
-- ============================================================================

CREATE TABLE IF NOT EXISTS store_imports (
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

CREATE TABLE IF NOT EXISTS camera_installation_imports (
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

-- ============================================================================
-- STEP 6: Rename tables if they haven't been renamed yet
-- ============================================================================

-- Rename subscriber_imports to legal_entity_imports (if not already done)
SET @table_exists := (SELECT COUNT(*) FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
                      AND TABLE_NAME = 'subscriber_imports');
SET @sqlstmt := IF(@table_exists > 0, 'RENAME TABLE subscriber_imports TO legal_entity_imports', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Rename subscriber_contracts to legal_entity_contracts (if not already done)
SET @table_exists := (SELECT COUNT(*) FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
                      AND TABLE_NAME = 'subscriber_contracts');
SET @sqlstmt := IF(@table_exists > 0, 'RENAME TABLE subscriber_contracts TO legal_entity_contracts', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Update column name in legal_entity_contracts (if it exists and hasn't been updated)
SET @column_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
                       AND TABLE_NAME = 'legal_entity_contracts'
                       AND COLUMN_NAME = 'subscriber_id');
SET @sqlstmt := IF(@column_exists > 0,
                   'ALTER TABLE legal_entity_contracts CHANGE COLUMN subscriber_id legal_entity_id INT NOT NULL',
                   'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- ============================================================================
-- Verification
-- ============================================================================

SELECT 'Migration Complete!' as status;

SELECT 
    'Legal Entities' as entity_type,
    COUNT(*) as count
FROM legal_entities

UNION ALL

SELECT 
    'Stores' as entity_type,
    COUNT(*) as count
FROM stores;

