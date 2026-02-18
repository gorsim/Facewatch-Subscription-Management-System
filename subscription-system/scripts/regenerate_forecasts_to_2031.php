<?php
/**
 * Regenerate Forecast Invoices Through to 31/3/31
 * This script deletes existing forecast invoices and regenerates them through to 31st March 2031
 */

// Enable error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/InvoiceGenerationService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';
require_once __DIR__ . '/../app/Services/InvoiceAutoGenerationService.php';

use App\Database;
use App\Services\InvoiceAutoGenerationService;

echo "🔮 Regenerating Forecast Invoices Through to 31/3/31" . PHP_EOL;
echo str_repeat("=", 60) . PHP_EOL . PHP_EOL;

$db = Database::getInstance();
$autoGenService = new InvoiceAutoGenerationService();

// Step 1: Delete all existing forecast invoices
echo "Step 1: Deleting existing forecast invoices..." . PHP_EOL;
$deleteResult = $db->query("DELETE FROM invoices WHERE is_forecast = TRUE");
echo "✅ Deleted existing forecast invoices" . PHP_EOL . PHP_EOL;

// Step 2: Get all non-forecast invoices
$invoices = $db->fetchAll("
    SELECT id, invoice_number, invoice_date, legal_entity_id
    FROM invoices
    WHERE is_forecast = FALSE OR is_forecast IS NULL
    ORDER BY invoice_date DESC
");

echo "Step 2: Found " . count($invoices) . " existing invoices" . PHP_EOL . PHP_EOL;

$totalGenerated = 0;
$errors = [];

foreach ($invoices as $invoice) {
    echo "Processing {$invoice['invoice_number']} (ID: {$invoice['id']}, Date: {$invoice['invoice_date']})... ";

    try {
        $forecastIds = $autoGenService->generateForecastInvoices($invoice['id']);
        $count = count($forecastIds);
        $totalGenerated += $count;

        if ($count > 0) {
            echo "✅ Generated {$count} forecasts through to 31/3/31" . PHP_EOL;
        } else {
            echo "⚠️  No forecasts generated (may be terminated or past 31/3/31)" . PHP_EOL;
        }

    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
        echo $error . PHP_EOL;
        $errors[] = [
            'invoice' => $invoice['invoice_number'],
            'error' => $e->getMessage()
        ];
    }
}

echo PHP_EOL . str_repeat("=", 60) . PHP_EOL;
echo "✅ Complete!" . PHP_EOL;
echo "   Total forecast invoices generated: {$totalGenerated}" . PHP_EOL;

if (!empty($errors)) {
    echo PHP_EOL . "⚠️  Errors encountered:" . PHP_EOL;
    foreach ($errors as $error) {
        echo "   - {$error['invoice']}: {$error['error']}" . PHP_EOL;
    }
}

echo PHP_EOL . "🎉 All forecasts now extend through to 31st March 2031!" . PHP_EOL;

