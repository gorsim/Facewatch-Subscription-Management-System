-- Migration: Camera Movement Tracking & Audit Trail
-- Date: 2026-02-19
-- Purpose: Track camera movements between stores and generate Xero correction reports

-- Table to track camera movements between stores
CREATE TABLE IF NOT EXISTS camera_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camera_installation_id INT NOT NULL COMMENT 'The camera installation record',
    safr_code VARCHAR(50) NOT NULL COMMENT 'SAFR code of the camera that moved',
    camera_name VARCHAR(100) COMMENT 'Name/description of the camera',
    
    -- Movement details
    from_store_id INT NOT NULL COMMENT 'Store the camera was removed from',
    from_store_name VARCHAR(255) NOT NULL COMMENT 'Store name at time of movement',
    to_store_id INT NOT NULL COMMENT 'Store the camera was installed at',
    to_store_name VARCHAR(255) NOT NULL COMMENT 'Store name at time of movement',
    
    -- Dates
    removal_date DATE NOT NULL COMMENT 'Date camera was removed from old store',
    installation_date DATE NOT NULL COMMENT 'Date camera was installed at new store',
    
    -- Import tracking
    import_session_id INT COMMENT 'ID of the import session that detected this movement',
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the movement was detected',
    detected_by VARCHAR(100) COMMENT 'User who ran the import',
    
    -- Invoice impact tracking
    affected_invoices_count INT DEFAULT 0 COMMENT 'Number of invoices affected by this movement',
    draft_invoices_updated INT DEFAULT 0 COMMENT 'Number of draft invoices automatically updated',
    issued_invoices_flagged INT DEFAULT 0 COMMENT 'Number of issued invoices flagged for Xero correction',
    
    -- Status
    xero_correction_status ENUM('pending', 'exported', 'corrected', 'not_required') DEFAULT 'pending',
    xero_correction_notes TEXT COMMENT 'Notes about Xero corrections made',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (camera_installation_id) REFERENCES camera_installations(id) ON DELETE CASCADE,
    FOREIGN KEY (from_store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (to_store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (import_session_id) REFERENCES camera_installation_imports(id) ON DELETE SET NULL,
    
    INDEX idx_safr_code (safr_code),
    INDEX idx_from_store (from_store_id),
    INDEX idx_to_store (to_store_id),
    INDEX idx_removal_date (removal_date),
    INDEX idx_installation_date (installation_date),
    INDEX idx_xero_status (xero_correction_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to track invoice allocation changes due to camera movements
CREATE TABLE IF NOT EXISTS camera_movement_invoice_impacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camera_movement_id INT NOT NULL,
    invoice_id INT NOT NULL,
    
    -- Invoice details at time of movement
    invoice_number VARCHAR(50) NOT NULL,
    invoice_status VARCHAR(50) NOT NULL,
    invoice_date DATE,
    
    -- Change details
    old_store_id INT NOT NULL,
    old_store_name VARCHAR(255) NOT NULL,
    new_store_id INT NOT NULL,
    new_store_name VARCHAR(255) NOT NULL,
    
    -- Action taken
    action_taken ENUM('auto_updated', 'flagged_for_xero', 'no_action') NOT NULL,
    action_notes TEXT,
    
    -- Xero correction tracking
    requires_xero_correction BOOLEAN DEFAULT FALSE,
    xero_corrected BOOLEAN DEFAULT FALSE,
    xero_corrected_at TIMESTAMP NULL,
    xero_corrected_by VARCHAR(100),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (camera_movement_id) REFERENCES camera_movements(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (old_store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (new_store_id) REFERENCES stores(id) ON DELETE CASCADE,
    
    INDEX idx_movement (camera_movement_id),
    INDEX idx_invoice (invoice_id),
    INDEX idx_requires_xero (requires_xero_correction),
    INDEX idx_xero_corrected (xero_corrected)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add column to camera_installation_imports to track movements detected
ALTER TABLE camera_installation_imports
ADD COLUMN IF NOT EXISTS movements_detected INT DEFAULT 0 COMMENT 'Number of camera movements detected in this import',
ADD COLUMN IF NOT EXISTS invoices_auto_updated INT DEFAULT 0 COMMENT 'Number of draft invoices automatically updated',
ADD COLUMN IF NOT EXISTS invoices_flagged_for_xero INT DEFAULT 0 COMMENT 'Number of issued invoices flagged for Xero correction';

