<?php
/**
 * Database Setup Script
 * Runs all migrations in order to set up the complete database schema
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

echo "=== Facewatch Subscription Management System - Database Setup ===\n\n";

$db = Database::getInstance();

// List of migrations to run in order
$migrations = [
    '001_create_initial_schema.sql',
    '002_restructure_for_stores.sql',
    '004_safe_restructure.sql',
    '006_invoice_reconciliation_redesign.sql',
    '007_add_rates_to_legal_entities.sql',
    '011_rename_xero_customer_number_to_company_name.sql',
    '013_complete_setup_with_company_name.sql',
    '014_add_payment_frequency.sql',
    '020_create_rate_history_system.sql',
    '021_update_pricing_structure.sql',
    '022_add_entity_specific_pricing.sql',
    '024_add_store_level_pricing.sql',
    '025_fix_pricing_model_to_entity_level.sql',
    '026_add_payment_terms_days.sql',
    '027_invoice_generation_foundation.sql',
    '028_update_invoice_status_enum.sql',
    '031_add_deleted_to_audit_log_action.sql'
];

$successCount = 0;
$failCount = 0;
$skippedCount = 0;

foreach ($migrations as $migration) {
    // Check multiple locations for migration files
    $possiblePaths = [
        __DIR__ . '/database/migrations/' . $migration,
        __DIR__ . '/../database/migrations/' . $migration,
    ];

    $filePath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $filePath = $path;
            break;
        }
    }

    if (!$filePath) {
        echo "⚠️  SKIPPED: $migration (file not found)\n";
        $skippedCount++;
        continue;
    }

    echo "Running: $migration ... ";
    
    try {
        $sql = file_get_contents($filePath);
        
        // Split by semicolons but be careful with stored procedures
        $statements = [];
        $buffer = '';
        $inDelimiter = false;
        
        foreach (explode("\n", $sql) as $line) {
            // Check for DELIMITER command
            if (preg_match('/^\s*DELIMITER\s+(.+)$/i', $line, $matches)) {
                $inDelimiter = ($matches[1] !== ';');
                continue;
            }
            
            $buffer .= $line . "\n";
            
            // If we're not in a delimiter block and line ends with ;, it's a complete statement
            if (!$inDelimiter && preg_match('/;\s*$/', $line)) {
                $statement = trim($buffer);
                if (!empty($statement) && !preg_match('/^--/', $statement) && !preg_match('/^USE /', $statement)) {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }
        
        // Add any remaining buffer
        if (!empty(trim($buffer))) {
            $statements[] = trim($buffer);
        }
        
        // Execute each statement
        foreach ($statements as $statement) {
            if (empty($statement)) continue;
            
            try {
                $db->query($statement);
            } catch (Exception $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    throw $e;
                }
            }
        }
        
        echo "✅ SUCCESS\n";
        $successCount++;
        
    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
        $failCount++;
    }
}

echo "\n=== Summary ===\n";
echo "✅ Successful: $successCount\n";
echo "❌ Failed: $failCount\n";
echo "⚠️  Skipped: $skippedCount\n";

// Create default admin user if it doesn't exist
echo "\n=== Creating Default Admin User ===\n";
try {
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
        echo "✅ Admin user created (username: admin, password: admin123)\n";
        echo "⚠️  IMPORTANT: Please change the admin password after first login!\n";
    } else {
        echo "ℹ️  Admin user already exists\n";
    }
} catch (Exception $e) {
    echo "❌ Failed to create admin user: " . $e->getMessage() . "\n";
}

echo "\n=== Database Setup Complete! ===\n";
echo "You can now log in to the system.\n\n";

