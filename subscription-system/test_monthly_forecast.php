<?php
/**
 * Test script to regenerate forecast invoices for a specific invoice
 * This will delete existing forecasts and regenerate them with the new logic
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use App\Database;
use App\Services\InvoiceAutoGenerationService;

// Get the invoice number from command line or use default
$invoiceNumber = $argv[1] ?? 'INV-023';

$db = Database::getInstance();

// Find the invoice
$invoice = $db->fetchOne("
    SELECT i.*, le.payment_frequency 
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE i.invoice_number = :number
", ['number' => $invoiceNumber]);

if (!$invoice) {
    die("Invoice $invoiceNumber not found!\n");
}

echo "Found invoice: {$invoice['invoice_number']}\n";
echo "Payment frequency: {$invoice['payment_frequency']}\n";
echo "Invoice date: {$invoice['invoice_date']}\n\n";

// Delete existing forecast invoices
$deleted = $db->execute("
    DELETE FROM invoices 
    WHERE parent_invoice_id = :parent_id 
    AND is_forecast = 1
", ['parent_id' => $invoice['id']]);

echo "Deleted existing forecast invoices\n\n";

// Regenerate forecasts
$autoGenService = new InvoiceAutoGenerationService();
$forecastIds = $autoGenService->generateForecastInvoices($invoice['id']);

echo "Generated " . count($forecastIds) . " forecast invoices!\n\n";

// Show the first 10 forecasts
$forecasts = $db->fetchAll("
    SELECT invoice_number, invoice_date, invoice_amount, payment_frequency
    FROM invoices
    WHERE parent_invoice_id = :parent_id
    AND is_forecast = 1
    ORDER BY invoice_date ASC
    LIMIT 10
", ['parent_id' => $invoice['id']]);

echo "First 10 forecast invoices:\n";
echo str_repeat('-', 80) . "\n";
printf("%-15s %-15s %-15s %-15s\n", "Invoice #", "Date", "Amount", "Frequency");
echo str_repeat('-', 80) . "\n";

foreach ($forecasts as $forecast) {
    printf("%-15s %-15s £%-14.2f %-15s\n", 
        $forecast['invoice_number'],
        $forecast['invoice_date'],
        $forecast['invoice_amount'],
        $forecast['payment_frequency']
    );
}

echo "\nDone! ✅\n";

