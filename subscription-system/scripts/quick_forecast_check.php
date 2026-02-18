<?php
/**
 * Quick forecast check - optimized to avoid timeouts
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "=== Quick Forecast Dashboard Check ===\n\n";

// Simple counts
echo "Invoice Counts:\n";
$counts = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_forecast = 1 THEN 1 ELSE 0 END) as forecasts,
        SUM(CASE WHEN is_forecast = 0 AND invoice_status = 'draft' THEN 1 ELSE 0 END) as drafts,
        SUM(CASE WHEN is_forecast = 0 AND invoice_status = 'issued' THEN 1 ELSE 0 END) as issued
    FROM invoices
");

echo "  Total: " . number_format($counts['total']) . "\n";
echo "  Forecasts: " . number_format($counts['forecasts']) . "\n";
echo "  Drafts: " . number_format($counts['drafts']) . "\n";
echo "  Issued: " . number_format($counts['issued']) . "\n\n";

// Check INV-135 chain specifically
echo "=== INV-135 Chain (First 10) ===\n";
$inv135 = $db->fetchOne("SELECT id FROM invoices WHERE invoice_number = 'INV-135'");
if ($inv135) {
    $chain = $db->fetchAll("
        SELECT invoice_number, invoice_date, is_forecast
        FROM invoices
        WHERE parent_invoice_id = :parent_id
        ORDER BY invoice_date
        LIMIT 10
    ", ['parent_id' => $inv135['id']]);
    
    foreach ($chain as $inv) {
        $type = $inv['is_forecast'] ? 'FORECAST' : 'ACTUAL';
        echo "  " . $inv['invoice_number'] . ": " . $inv['invoice_date'] . " - " . $type . "\n";
    }
}

// Upcoming conversions (next 7 days only)
echo "\n=== Upcoming Conversions (Next 7 Days) ===\n";
$upcoming = $db->fetchAll("
    SELECT invoice_number, invoice_date, invoice_amount
    FROM invoices
    WHERE is_forecast = 1
    AND invoice_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY invoice_date
    LIMIT 10
");

if (empty($upcoming)) {
    echo "No forecast invoices due in the next 7 days.\n";
} else {
    foreach ($upcoming as $inv) {
        echo "  " . $inv['invoice_number'] . " - " . $inv['invoice_date'] . " - £" . 
             number_format($inv['invoice_amount'], 2) . "\n";
    }
}

echo "\n✅ Quick check complete\n";
echo "\nTo view in browser, go to:\n";
echo "http://localhost:8888/subscription-system/index.php?page=invoices&status=forecast\n";

