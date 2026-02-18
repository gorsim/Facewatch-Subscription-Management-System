<?php
require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "=== Checking xero_imported_invoices table structure ===\n\n";

$columns = $db->fetchAll("SHOW COLUMNS FROM xero_imported_invoices");

echo "Columns in xero_imported_invoices:\n";
foreach ($columns as $column) {
    echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
}

