<?php
/**
 * Run Database Migration - Invoice Reconciliation Redesign
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "Running migration: Invoice Reconciliation Redesign\n";
echo "=================================\n\n";

$success = 0;
$failed = 0;

// Step 1: Rename fcst_lookup_code to xero_customer_number
echo "Step 1: Renaming fcst_lookup_code to xero_customer_number...\n";
try {
    // Check if old column exists
    echo "  Checking for fcst_lookup_code column...\n";
    $columns = $db->fetchAll("SHOW COLUMNS FROM legal_entities LIKE 'fcst_lookup_code'");
    echo "  Found " . count($columns) . " matching columns\n";

    if (count($columns) > 0) {
        echo "  Renaming column...\n";
        $db->query("ALTER TABLE legal_entities CHANGE COLUMN fcst_lookup_code xero_customer_number VARCHAR(255) NULL");
        echo "✓ Column renamed from fcst_lookup_code to xero_customer_number\n";
        $success++;
    } else {
        // Check if new column already exists
        $newColumns = $db->fetchAll("SHOW COLUMNS FROM legal_entities LIKE 'xero_customer_number'");
        if (count($newColumns) > 0) {
            echo "→ Column already renamed (xero_customer_number exists)\n";
        } else {
            echo "✗ Neither fcst_lookup_code nor xero_customer_number found\n";
            $failed++;
        }
    }
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    echo "  Error details: " . print_r($e, true) . "\n";
    $failed++;
}

// Add index
try {
    $db->query("CREATE INDEX idx_xero_customer_number ON legal_entities(xero_customer_number)");
    echo "✓ Index created on xero_customer_number\n";
    $success++;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "→ Index already exists\n";
    } else {
        echo "✗ Index creation failed: " . $e->getMessage() . "\n";
        $failed++;
    }
}

// Step 2: Update invoices table - add xero_customer_number
echo "\nStep 2: Updating invoices table...\n";
try {
    $columns = $db->fetchAll("SHOW COLUMNS FROM invoices LIKE 'xero_customer_number'");
    if (count($columns) == 0) {
        $db->query("ALTER TABLE invoices ADD COLUMN xero_customer_number VARCHAR(255) NULL AFTER legal_entity_id");
        echo "✓ Added xero_customer_number column\n";
        $success++;
    } else {
        echo "→ xero_customer_number column already exists\n";
    }
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    $failed++;
}

// Add expected_amount
try {
    $columns = $db->fetchAll("SHOW COLUMNS FROM invoices LIKE 'expected_amount'");
    if (count($columns) == 0) {
        $db->query("ALTER TABLE invoices ADD COLUMN expected_amount DECIMAL(10,2) NULL");
        echo "✓ Added expected_amount column\n";
        $success++;
    } else {
        echo "→ expected_amount column already exists\n";
    }
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    $failed++;
}

// Add variance
try {
    $columns = $db->fetchAll("SHOW COLUMNS FROM invoices LIKE 'variance'");
    if (count($columns) == 0) {
        $db->query("ALTER TABLE invoices ADD COLUMN variance DECIMAL(10,2) NULL");
        echo "✓ Added variance column\n";
        $success++;
    } else {
        echo "→ variance column already exists\n";
    }
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    $failed++;
}

// Add reconciliation_status
try {
    $columns = $db->fetchAll("SHOW COLUMNS FROM invoices LIKE 'reconciliation_status'");
    if (count($columns) == 0) {
        $db->query("ALTER TABLE invoices ADD COLUMN reconciliation_status ENUM('matched', 'under_charged', 'over_charged', 'pending') DEFAULT 'pending'");
        echo "✓ Added reconciliation_status column\n";
        $success++;
    } else {
        echo "→ reconciliation_status column already exists\n";
    }
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    $failed++;
}

// Step 3: Populate xero_customer_number from legal_entities
echo "\nStep 3: Populating xero_customer_number in invoices...\n";
try {
    $result = $db->query("UPDATE invoices i JOIN legal_entities le ON i.legal_entity_id = le.id SET i.xero_customer_number = le.xero_customer_number WHERE i.xero_customer_number IS NULL");
    echo "✓ Populated xero_customer_number\n";
    $success++;
} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
    $failed++;
}

// Step 4: Add unique constraint
echo "\nStep 4: Adding unique constraint on invoices...\n";
try {
    $db->query("ALTER TABLE invoices ADD UNIQUE KEY unique_xero_invoice (xero_customer_number, invoice_date)");
    echo "✓ Unique constraint added\n";
    $success++;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "→ Unique constraint already exists\n";
    } else {
        echo "✗ Failed: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n=================================\n";
echo "Migration Part 1 Complete!\n";
echo "Successful: $success\n";
echo "Failed: $failed\n";
echo "=================================\n";
echo "\nNow run: php subscription-system/create_allocations_table.php\n";

