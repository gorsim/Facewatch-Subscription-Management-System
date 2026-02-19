<?php
/**
 * AWS Database Setup Script
 * Creates all tables from scratch in the correct order
 * Safe to run on a fresh database
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

echo "=== Facewatch Subscription Management System - AWS Database Setup ===\n\n";

$db = Database::getInstance();

try {
    echo "Creating database schema...\n\n";

    // Execute the complete schema creation
    $sql = "
    -- ============================================================================
    -- 1. CORE TABLES
    -- ============================================================================

    -- Users table for authentication
    CREATE TABLE IF NOT EXISTS users (
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

    -- Legal Entities (main customer entities)
    CREATE TABLE IF NOT EXISTS legal_entities (
        id INT PRIMARY KEY AUTO_INCREMENT,
        legal_entity_id VARCHAR(50) UNIQUE,
        legal_entity_name VARCHAR(255) NOT NULL,
        xero_company_name VARCHAR(255),
        installation_date DATE,
        termination_date DATE,
        category VARCHAR(100),
        sales_credit VARCHAR(100),
        pricing_type ENUM('default', 'custom') DEFAULT 'default',
        pricing_model ENUM('volume_based', 'first_plus_additional') DEFAULT 'volume_based',
        payment_frequency ENUM('annual', 'quarterly', 'monthly') DEFAULT 'annual',
        payment_terms_days INT DEFAULT 30,
        main_camera_rate DECIMAL(10,2) NULL DEFAULT 0.00,
        additional_camera_rate DECIMAL(10,2) NULL DEFAULT 0.00,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_legal_entity_id (legal_entity_id),
        INDEX idx_legal_entity_name (legal_entity_name),
        INDEX idx_xero_company_name (xero_company_name),
        INDEX idx_pricing_type (pricing_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Stores (belong to Legal Entities)
    CREATE TABLE IF NOT EXISTS stores (
        id INT PRIMARY KEY AUTO_INCREMENT,
        legal_entity_id INT NOT NULL,
        store_id VARCHAR(50) UNIQUE,
        store_name VARCHAR(255) NOT NULL,
        installation_date DATE,
        termination_date DATE,
        category VARCHAR(100),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE,
        INDEX idx_legal_entity (legal_entity_id),
        INDEX idx_store_id (store_id),
        INDEX idx_store_name (store_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Camera Installations (detailed tracking at store level)
    CREATE TABLE IF NOT EXISTS camera_installations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        store_id INT NOT NULL,
        installation_date DATE NOT NULL,
        removal_date DATE NULL,
        camera_type ENUM('main', 'additional') NOT NULL,
        camera_name VARCHAR(100),
        safr_code VARCHAR(50),
        invoice_id INT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        INDEX idx_store (store_id),
        INDEX idx_installation_date (installation_date),
        INDEX idx_safr_code (safr_code),
        INDEX idx_active (removal_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Invoices
    CREATE TABLE IF NOT EXISTS invoices (
        id INT PRIMARY KEY AUTO_INCREMENT,
        legal_entity_id INT NOT NULL,
        invoice_number VARCHAR(100) NOT NULL UNIQUE,
        invoice_date DATE NOT NULL,
        due_date DATE,
        main_cameras INT DEFAULT 0,
        additional_cameras INT DEFAULT 0,
        total_cameras INT GENERATED ALWAYS AS (main_cameras + additional_cameras) STORED,
        invoice_amount DECIMAL(12,2) NOT NULL,
        payment_frequency ENUM('annual', 'quarterly', 'monthly') DEFAULT 'annual',
        invoice_status ENUM('draft', 'issued', 'reconciled_to_xero', 'forecast') DEFAULT 'draft',
        is_forecast BOOLEAN DEFAULT FALSE,
        parent_invoice_id INT NULL,
        is_vat_exclusive BOOLEAN DEFAULT TRUE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE,
        INDEX idx_legal_entity (legal_entity_id),
        INDEX idx_invoice_number (invoice_number),
        INDEX idx_invoice_date (invoice_date),
        INDEX idx_invoice_status (invoice_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Split and execute statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            $db->query($statement);
        }
    }

    echo "✅ Core tables created\n\n";




    // ============================================================================
    // 2. ADDITIONAL TABLES
    // ============================================================================

    echo "Creating additional tables...\n";

    // Run additional migration files
    $additionalMigrations = [
        'database/migrations/020_create_rate_history_system.sql',
        'database/migrations/027_invoice_generation_foundation.sql',
        'database/migrations/008_camera_movements_tracking.sql'
    ];

    foreach ($additionalMigrations as $migrationFile) {
        $filePath = __DIR__ . '/' . $migrationFile;
        if (file_exists($filePath)) {
            echo "Running: " . basename($migrationFile) . "... ";
            $sql = file_get_contents($filePath);
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $db->query($statement);
                    } catch (Exception $e) {
                        // Ignore "already exists" errors
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            echo "⚠️  " . $e->getMessage() . "\n";
                        }
                    }
                }
            }
            echo "✅\n";
        }
    }

    echo "\n✅ All tables created successfully!\n\n";

    // ============================================================================
    // 3. CREATE DEFAULT ADMIN USER
    // ============================================================================

    echo "Creating default admin user...\n";

    $existingUser = $db->fetchOne("SELECT id FROM users WHERE username = 'admin'");

    if (!$existingUser) {
        $db->insert('users', [
            'username' => 'admin',
            'email' => 'admin@facewatch.co.uk',
            'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
            'full_name' => 'System Administrator',
            'role' => 'admin',
            'is_active' => 1
        ]);
        echo "✅ Admin user created\n";
        echo "   Username: admin\n";
        echo "   Password: admin123\n";
        echo "   ⚠️  IMPORTANT: Change this password after first login!\n\n";
    } else {
        echo "ℹ️  Admin user already exists\n\n";
    }

    echo "=== Database Setup Complete! ===\n";
    echo "You can now access the system at your AWS URL\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
