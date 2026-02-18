<?php
/**
 * Check the status of forecast invoices in the database
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "=== Forecast Invoice Status Check ===\n\n";

// Count all invoices by type
$counts = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_forecast = 1 THEN 1 ELSE 0 END) as forecasts,
        SUM(CASE WHEN is_forecast = 0 THEN 1 ELSE 0 END) as actual
    FROM invoices
");

echo "Total invoices: {$counts['total']}\n";
echo "Forecast invoices (is_forecast=1): {$counts['forecasts']}\n";
echo "Actual invoices (is_forecast=0): {$counts['actual']}\n\n";

// Check INV-135 chain specifically
echo "=== INV-135 Chain Details ===\n";
$chain = $db->fetchAll("
    SELECT invoice_number, invoice_date, is_forecast, invoice_status, parent_invoice_id
    FROM invoices 
    WHERE parent_invoice_id = (SELECT id FROM invoices WHERE invoice_number = 'INV-135')
       OR invoice_number = 'INV-135'
    ORDER BY invoice_date
");

foreach ($chain as $inv) {
    $type = $inv['is_forecast'] ? 'FORECAST' : 'ACTUAL';
    echo "{$inv['invoice_number']}: {$inv['invoice_date']} - {$type} - {$inv['invoice_status']}\n";
}

echo "\n=== Sample of Forecast Invoices (if any) ===\n";
$forecasts = $db->fetchAll("
    SELECT invoice_number, invoice_date, invoice_status, parent_invoice_id
    FROM invoices 
    WHERE is_forecast = 1
    ORDER BY invoice_date
    LIMIT 10
");

if (empty($forecasts)) {
    echo "No forecast invoices found!\n";
} else {
    foreach ($forecasts as $f) {
        echo "{$f['invoice_number']}: {$f['invoice_date']} - {$f['invoice_status']}\n";
    }
}

