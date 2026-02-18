<?php
/**
 * Debug script to check recalculate status
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "=== RECALCULATE STATUS DEBUG ===\n\n";

// Check 1: Invoices missing forecasts
echo "Check 1: Invoices Missing Forecasts\n";
echo "------------------------------------\n";
$missingForecasts = $db->fetchAll("
    SELECT i.id, i.invoice_number, i.invoice_date, i.invoice_amount
    FROM invoices i
    WHERE (i.is_forecast = 0 OR i.is_forecast IS NULL)
    AND NOT EXISTS (
        SELECT 1 FROM invoices f 
        WHERE f.parent_invoice_id = i.id 
        AND f.is_forecast = 1
    )
    ORDER BY i.invoice_date DESC
");

if (empty($missingForecasts)) {
    echo "✅ All invoices have forecasts!\n";
} else {
    echo "❌ Found " . count($missingForecasts) . " invoices missing forecasts:\n";
    foreach ($missingForecasts as $inv) {
        echo "  - {$inv['invoice_number']} (ID: {$inv['id']}, Date: {$inv['invoice_date']}, Amount: £" . number_format($inv['invoice_amount'], 2) . ")\n";
    }
}
echo "\n";

// Check 2: Forecast invoices out of sync with parent
echo "Check 2: Forecast Invoices Out of Sync\n";
echo "---------------------------------------\n";
$outOfSyncForecasts = $db->fetchAll("
    SELECT f.id, f.invoice_number, f.invoice_amount as forecast_amount, 
           p.invoice_number as parent_number, p.invoice_amount as parent_amount
    FROM invoices f
    JOIN invoices p ON f.parent_invoice_id = p.id
    WHERE f.is_forecast = 1
    AND f.invoice_amount != p.invoice_amount
    ORDER BY p.invoice_number, f.forecast_year
");

if (empty($outOfSyncForecasts)) {
    echo "✅ All forecast invoices match their parents!\n";
} else {
    echo "❌ Found " . count($outOfSyncForecasts) . " forecast invoices out of sync:\n";
    foreach ($outOfSyncForecasts as $inv) {
        echo "  - {$inv['invoice_number']} (£" . number_format($inv['forecast_amount'], 2) . ") vs Parent {$inv['parent_number']} (£" . number_format($inv['parent_amount'], 2) . ")\n";
    }
}
echo "\n";

// Check 3: Parent invoices where amount doesn't match allocated cameras
echo "Check 3: Parent Invoices Out of Sync with Cameras\n";
echo "---------------------------------------------------\n";
$outOfSyncParents = $db->fetchAll("
    SELECT i.id, i.invoice_number, i.invoice_amount, 
           COALESCE(ica.total, 0) as camera_total,
           ABS(i.invoice_amount - COALESCE(ica.total, 0)) as difference
    FROM invoices i
    LEFT JOIN (
        SELECT invoice_id, SUM(price_charged) as total
        FROM invoice_camera_allocations
        WHERE removed_date IS NULL
        GROUP BY invoice_id
    ) ica ON i.id = ica.invoice_id
    WHERE (i.is_forecast = 0 OR i.is_forecast IS NULL)
    AND (
        (ica.total IS NULL AND i.invoice_amount != 0) OR
        (ica.total IS NOT NULL AND ABS(i.invoice_amount - ica.total) > 0.01)
    )
    ORDER BY i.invoice_date DESC
");

if (empty($outOfSyncParents)) {
    echo "✅ All parent invoices match their camera allocations!\n";
} else {
    echo "❌ Found " . count($outOfSyncParents) . " parent invoices out of sync:\n";
    foreach ($outOfSyncParents as $inv) {
        echo "  - {$inv['invoice_number']} (Invoice: £" . number_format($inv['invoice_amount'], 2) . " vs Cameras: £" . number_format($inv['camera_total'], 2) . ", Diff: £" . number_format($inv['difference'], 2) . ")\n";
    }
}
echo "\n";

// Summary
echo "=== SUMMARY ===\n";
$needsRecalculate = (!empty($missingForecasts) || !empty($outOfSyncForecasts) || !empty($outOfSyncParents));
if ($needsRecalculate) {
    echo "🟢 BUTTON SHOULD BE GREEN AND PULSING\n";
    echo "Action needed: " . (count($missingForecasts) + count($outOfSyncForecasts) + count($outOfSyncParents)) . " issues found\n";
} else {
    echo "🟠 BUTTON SHOULD BE ORANGE (NORMAL)\n";
    echo "Everything is up to date!\n";
}

