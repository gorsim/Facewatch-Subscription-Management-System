-- Facewatch Subscription Management System
-- Initial Database Schema Migration
-- Created: 2026-02-13

-- ============================================================================
-- 1. MASTER DATA TABLES
-- ============================================================================

-- Users table for authentication and authorization
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'viewer') DEFAULT 'viewer',
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscribers (customers)
CREATE TABLE subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_name VARCHAR(255) NOT NULL,
    legal_entity_name VARCHAR(255),
    fcst_lookup_code VARCHAR(50) UNIQUE,
    sales_credit VARCHAR(100),
    category VARCHAR(100),
    installation_date DATE,
    termination_date DATE,
    payment_terms_days INT DEFAULT 30,
    pricing_basis ENUM('rent', 'purchase') DEFAULT 'rent',
    is_legacy_pricing BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_subscriber_name (subscriber_name),
    INDEX idx_legal_entity (legal_entity_name),
    INDEX idx_fcst_lookup (fcst_lookup_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pricing tiers with historical tracking
CREATE TABLE pricing_tiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    effective_date DATE NOT NULL,
    min_cameras INT NOT NULL,
    max_cameras INT,
    annual_price_per_camera DECIMAL(10,2) NOT NULL,
    monthly_price_per_camera DECIMAL(10,2) NOT NULL,
    tier_name VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_effective_date (effective_date),
    INDEX idx_camera_range (min_cameras, max_cameras)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Annual inflation rates
CREATE TABLE inflation_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    effective_date DATE NOT NULL UNIQUE,
    inflation_percentage DECIMAL(5,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscriber contracts and pricing terms
CREATE TABLE subscriber_contracts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_id INT NOT NULL,
    effective_date DATE NOT NULL,
    invoice_date DATE,
    first_inflation_date DATE,
    payment_frequency ENUM('annual', 'quarterly', 'monthly') DEFAULT 'annual',
    main_camera_rate DECIMAL(10,2),
    additional_camera_rate DECIMAL(10,2),
    additional_camera_percentage DECIMAL(5,2),
    is_hardcoded_rate BOOLEAN DEFAULT FALSE,
    minimum_term_years INT DEFAULT 3,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_subscriber_effective (subscriber_id, effective_date),
    INDEX idx_invoice_date (invoice_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. OPERATIONAL DATA TABLES
-- ============================================================================

-- Monthly camera installation tracking
CREATE TABLE camera_counts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_id INT NOT NULL,
    month_date DATE NOT NULL,
    main_cameras_installed INT DEFAULT 0,
    additional_cameras_installed INT DEFAULT 0,
    cumulative_main_cameras INT DEFAULT 0,
    cumulative_additional_cameras INT DEFAULT 0,
    cumulative_total_cameras INT GENERATED ALWAYS AS
        (cumulative_main_cameras + cumulative_additional_cameras) STORED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_subscriber_month (subscriber_id, month_date),
    INDEX idx_month_date (month_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Tracks actual camera installations by month';

-- Invoices from Xero
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_id INT NOT NULL,
    invoice_number VARCHAR(100) NOT NULL UNIQUE,
    invoice_date DATE NOT NULL,
    due_date DATE,
    main_cameras INT DEFAULT 0,
    additional_cameras INT DEFAULT 0,
    total_cameras INT GENERATED ALWAYS AS (main_cameras + additional_cameras) STORED,
    invoice_amount DECIMAL(12,2) NOT NULL,
    payment_status ENUM('unpaid', 'paid', 'overdue', 'cancelled') DEFAULT 'unpaid',
    payment_date DATE,
    payment_frequency ENUM('annual', 'quarterly', 'monthly') DEFAULT 'annual',
    is_vat_exclusive BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_subscriber_date (subscriber_id, invoice_date),
    INDEX idx_payment_status (payment_status),
    INDEX idx_invoice_date (invoice_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Monthly prepayment calculations
CREATE TABLE prepayments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    subscriber_id INT NOT NULL,
    calculation_date DATE NOT NULL,
    invoice_date DATE NOT NULL,
    invoice_amount DECIMAL(12,2) NOT NULL,
    payment_frequency ENUM('annual', 'quarterly', 'monthly') NOT NULL,
    days_since_invoice INT NOT NULL,
    days_in_period INT NOT NULL,
    days_remaining INT GENERATED ALWAYS AS (days_in_period - days_since_invoice) STORED,
    prepayment_balance DECIMAL(12,2) NOT NULL,
    pl_credit_amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_invoice_calc_date (invoice_id, calculation_date),
    INDEX idx_calculation_date (calculation_date),
    INDEX idx_subscriber_date (subscriber_id, calculation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cash flow forecasting
CREATE TABLE cash_flow_forecast (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    subscriber_id INT NOT NULL,
    expected_payment_date DATE NOT NULL,
    expected_amount DECIMAL(12,2) NOT NULL,
    confidence_level ENUM('high', 'medium', 'low') DEFAULT 'medium',
    is_overdue BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_expected_date (expected_payment_date),
    INDEX idx_subscriber (subscriber_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. RECONCILIATION & AUDIT TABLES
-- ============================================================================

-- Invoice reconciliation log
CREATE TABLE reconciliation_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    subscriber_id INT NOT NULL,
    check_date DATE NOT NULL,
    invoiced_main_cameras INT NOT NULL,
    invoiced_additional_cameras INT NOT NULL,
    installed_main_cameras INT NOT NULL,
    installed_additional_cameras INT NOT NULL,
    installation_variance_main INT GENERATED ALWAYS AS
        (invoiced_main_cameras - installed_main_cameras) STORED,
    installation_variance_additional INT GENERATED ALWAYS AS
        (invoiced_additional_cameras - installed_additional_cameras) STORED,
    expected_amount DECIMAL(12,2) NOT NULL,
    actual_amount DECIMAL(12,2) NOT NULL,
    variance DECIMAL(12,2) GENERATED ALWAYS AS (actual_amount - expected_amount) STORED,
    variance_percentage DECIMAL(5,2),
    status ENUM('ok', 'warning', 'error') DEFAULT 'ok',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_check_date (check_date),
    INDEX idx_status (status),
    INDEX idx_subscriber (subscriber_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Camera data import history
CREATE TABLE camera_imports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    filename VARCHAR(255),
    rows_imported INT DEFAULT 0,
    rows_failed INT DEFAULT 0,
    status ENUM('success', 'partial', 'failed') DEFAULT 'success',
    error_log TEXT,
    imported_by INT,

    FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_import_date (import_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Xero data import history
CREATE TABLE xero_imports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    filename VARCHAR(255),
    rows_imported INT DEFAULT 0,
    rows_failed INT DEFAULT 0,
    status ENUM('success', 'partial', 'failed') DEFAULT 'success',
    error_log TEXT,
    imported_by INT,

    FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_import_date (import_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- General audit log
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('insert', 'update', 'delete') NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User activity log
CREATE TABLE user_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
