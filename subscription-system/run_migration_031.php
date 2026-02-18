<?php
/**
 * Run Migration 031: Add Invoice Notes and Camera Fields
 * 
 * This migration adds:
 * - notes column to invoices table
 * - camera_name column to camera_installations table
 * - safr_code column to camera_installations table
 */

$config = require __DIR__ . '/config/database.php';

try {
    echo "Running Migration 031: Add Invoice Notes and Camera Fields\n";
    echo str_repeat("=", 70) . "\n\n";

    // Read the migration file
    $migrationFile = __DIR__ . '/migrations/031_add_invoice_notes_and_camera_fields.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: {$migrationFile}");
    }

    $sql = file_get_contents($migrationFile);

    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . $config['host'] . ";dbname=" . $config['database'] . ";charset=utf8mb4",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "Executing migration...\n\n";

    // Execute the entire SQL file at once (MySQL can handle multiple statements)
    $pdo->exec($sql);
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "✅ Migration 031 completed successfully!\n\n";
    
    echo "Changes made:\n";
    echo "  • Added 'notes' column to invoices table\n";
    echo "  • Added 'camera_name' column to camera_installations table\n";
    echo "  • Added 'safr_code' column to camera_installations table\n";
    echo "  • Added index on safr_code for faster lookups\n\n";
    
    echo "You can now:\n";
    echo "  • Add notes to invoices when creating or editing them\n";
    echo "  • Import camera data with Camera Name and SAFR Code fields\n";
    echo "  • Upload CSV files with these new columns\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

