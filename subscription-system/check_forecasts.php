<?php
/**
 * Check if forecast invoices exist for INV-082
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

// Get invoice INV-082
$invoice = $db->fetchOne("SELECT * FROM invoices WHERE invoice_number = 'INV-082'");

if (!$invoice) {
    echo "Invoice INV-082 not found!\n";
    exit;
}

echo "Invoice INV-082 Details:\n";
echo "  ID: {$invoice['id']}\n";
echo "  Legal Entity ID: {$invoice['legal_entity_id']}\n";
echo "  Invoice Date: {$invoice['invoice_date']}\n";
echo "  Invoice Amount: £" . number_format($invoice['invoice_amount'], 2) . "\n";
echo "  Payment Frequency: {$invoice['payment_frequency']}\n";
echo "  is_forecast: " . ($invoice['is_forecast'] ? 'TRUE' : 'FALSE') . "\n";
echo "  parent_invoice_id: " . ($invoice['parent_invoice_id'] ?? 'NULL') . "\n";
echo "\n";

// Check for forecast invoices
$forecasts = $db->fetchAll("
    SELECT id, invoice_number, invoice_date, invoice_amount, forecast_year
    FROM invoices
    WHERE parent_invoice_id = :parent_id
    AND is_forecast = 1
    ORDER BY invoice_date ASC
", ['parent_id' => $invoice['id']]);

echo "Forecast Invoices for INV-082:\n";
if (empty($forecasts)) {
    echo "  ❌ NO FORECAST INVOICES FOUND!\n";
    echo "\n";
    echo "This means forecast invoices were never created for this invoice.\n";
    echo "Forecast invoices should be created automatically when an invoice is created via the 'Create Invoice' form.\n";
    echo "If the invoice was created via the Invoice Generator, forecasts are NOT automatically created.\n";
} else {
    echo "  ✅ Found " . count($forecasts) . " forecast invoices:\n";
    foreach ($forecasts as $forecast) {
        echo "    - {$forecast['invoice_number']} (Year {$forecast['forecast_year']}) - {$forecast['invoice_date']} - £" . number_format($forecast['invoice_amount'], 2) . "\n";
    }
}

