<?php
/**
 * Delete Duplicate Forecasts
 * Removes forecasts that were incorrectly generated from converted forecast invoices
 */

require_once __DIR__ . '/../app/Database.php';

$db = App\Database::getInstance();

echo "🗑️  DELETING DUPLICATE FORECASTS\n";
echo "==========================================\n\n";

// Find INV-065 (the converted forecast)
$inv065 = $db->fetchOne("SELECT id FROM invoices WHERE invoice_number = 'INV-065'");

if (!$inv065) {
    echo "❌ INV-065 not found\n";
    exit(1);
}

// Find forecasts that have INV-065 as their parent
$duplicates = $db->fetchAll("
    SELECT id, invoice_number, invoice_date, parent_invoice_id
    FROM invoices
    WHERE parent_invoice_id = :parent_id
    AND is_forecast = 1
", ['parent_id' => $inv065['id']]);

echo "Found " . count($duplicates) . " duplicate forecasts created from INV-065\n\n";

if (!empty($duplicates)) {
    echo "First 5 duplicates:\n";
    for ($i = 0; $i < min(5, count($duplicates)); $i++) {
        $dup = $duplicates[$i];
        echo "  {$dup['invoice_number']} - {$dup['invoice_date']}\n";
    }
    
    echo "\n🔧 Deleting camera allocations for these forecasts...\n";
    $stmt = $db->query("
        DELETE FROM invoice_camera_allocations
        WHERE invoice_id IN (
            SELECT id FROM invoices
            WHERE parent_invoice_id = :parent_id
            AND is_forecast = 1
        )
    ", ['parent_id' => $inv065['id']]);
    echo "✅ Deleted " . $stmt->rowCount() . " camera allocations\n";
    
    echo "\n🔧 Deleting the duplicate forecast invoices...\n";
    $stmt = $db->query("
        DELETE FROM invoices
        WHERE parent_invoice_id = :parent_id
        AND is_forecast = 1
    ", ['parent_id' => $inv065['id']]);
    echo "✅ Deleted " . $stmt->rowCount() . " duplicate forecasts\n";
} else {
    echo "✅ No duplicates found - already clean!\n";
}

echo "\n\n📊 FINAL FORECAST COUNT\n";
echo "==========================================\n\n";

$totalForecasts = $db->fetchOne("SELECT COUNT(*) as count FROM invoices WHERE is_forecast = 1")['count'];
echo "Total forecast invoices: $totalForecasts\n";

$actualInvoices = $db->fetchOne("SELECT COUNT(*) as count FROM invoices WHERE is_forecast = 0")['count'];
echo "Total actual invoices: $actualInvoices\n";

// Show forecast chains
echo "\n\n📊 FORECAST CHAINS (First 5 actual invoices)\n";
echo "==========================================\n\n";

$chains = $db->fetchAll("
    SELECT 
        i.invoice_number,
        i.invoice_date,
        COUNT(f.id) as forecast_count
    FROM invoices i
    LEFT JOIN invoices f ON f.parent_invoice_id = i.id AND f.is_forecast = 1
    WHERE i.is_forecast = 0
    GROUP BY i.id
    ORDER BY i.invoice_date
    LIMIT 5
");

foreach ($chains as $chain) {
    echo "{$chain['invoice_number']} ({$chain['invoice_date']}): {$chain['forecast_count']} forecasts\n";
}

echo "\n✅ Cleanup complete!\n";

