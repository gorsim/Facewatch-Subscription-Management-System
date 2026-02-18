-- Migration: Create tables for Xero invoice import and matching
-- Date: 2026-02-16

-- Table to store imported Xero invoices temporarily
CREATE TABLE IF NOT EXISTS xero_import_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(255) NOT NULL,
    import_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    imported_by VARCHAR(255),
    total_invoices INT DEFAULT 0,
    matched_count INT DEFAULT 0,
    reconciled_count INT DEFAULT 0,
    status ENUM('pending', 'reviewing', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to store individual Xero invoices from import
CREATE TABLE IF NOT EXISTS xero_imported_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    xero_invoice_id VARCHAR(255) NOT NULL,
    xero_invoice_number VARCHAR(100) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50),
    
    -- Matching information
    matched_invoice_id INT NULL,
    match_confidence DECIMAL(5, 2) NULL COMMENT 'Confidence score 0-100',
    match_status ENUM('unmatched', 'auto_matched', 'suggested', 'manual_matched', 'rejected') DEFAULT 'unmatched',
    match_reason TEXT COMMENT 'Why this match was made',
    
    -- Reconciliation
    is_reconciled BOOLEAN DEFAULT FALSE,
    reconciled_at DATETIME NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (session_id) REFERENCES xero_import_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    
    INDEX idx_session (session_id),
    INDEX idx_xero_invoice_id (xero_invoice_id),
    INDEX idx_contact_name (contact_name),
    INDEX idx_invoice_date (invoice_date),
    INDEX idx_match_status (match_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to store matching rules and tolerances
CREATE TABLE IF NOT EXISTS matching_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    rule_type ENUM('entity_name', 'date', 'amount') NOT NULL,
    tolerance_value VARCHAR(50) NOT NULL COMMENT 'e.g., "3 days", "5%", "exact"',
    score_weight INT NOT NULL COMMENT 'Points awarded (0-100)',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default matching rules
INSERT INTO matching_rules (rule_name, rule_type, tolerance_value, score_weight) VALUES
('Entity Name - Exact Match', 'entity_name', 'exact', 40),
('Entity Name - High Similarity', 'entity_name', '90%', 35),
('Entity Name - Medium Similarity', 'entity_name', '75%', 25),
('Date - Exact Match', 'date', '0 days', 30),
('Date - 1-3 Days', 'date', '3 days', 25),
('Date - 4-7 Days', 'date', '7 days', 15),
('Amount - Exact Match', 'amount', '0%', 30),
('Amount - Within 2%', 'amount', '2%', 25),
('Amount - Within 5%', 'amount', '5%', 20);

-- Table to log all matching attempts for audit trail
CREATE TABLE IF NOT EXISTS matching_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    xero_invoice_id INT NOT NULL,
    system_invoice_id INT NULL,
    match_score DECIMAL(5, 2),
    match_details JSON COMMENT 'Detailed breakdown of score',
    action ENUM('auto_matched', 'suggested', 'accepted', 'rejected', 'manual') NOT NULL,
    performed_by VARCHAR(255),
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (session_id) REFERENCES xero_import_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (xero_invoice_id) REFERENCES xero_imported_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (system_invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

