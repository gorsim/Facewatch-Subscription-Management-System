<?php
/**
 * Regenerate Forecast Invoices with Fixed Date Calculation
 * This script deletes all existing forecast invoices and regenerates them
 * using the new date calculation logic that preserves day-of-month
 * 
 * Usage:
 *   php scripts/regenerate_forecasts_with_fixed_dates.php
 */

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/InvoiceAutoGenerationService.php';
require_once __DIR__ . '/../app/Services/InvoiceGenerationService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';

use App\Services\InvoiceAutoGenerationService;

echo "=== Regenerate Forecast Invoices with Fixed Dates ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

$db = App\Database::getInstance();
$autoGenService = new InvoiceAutoGenerationService();

try {
    // Step 1: Delete all existing forecast invoices
    echo "Step 1: Deleting existing forecast invoices...\n";
    $result = $db->query("DELETE FROM invoice_camera_allocations WHERE invoice_id IN (SELECT id FROM invoices WHERE is_forecast = 1)");
    echo "  Deleted camera allocations for forecast invoices\n";
    
    $result = $db->query("DELETE FROM invoice_generation_log WHERE invoice_id IN (SELECT id FROM invoices WHERE is_forecast = 1)");
    echo "  Deleted generation logs for forecast invoices\n";
    
    $result = $db->query("DELETE FROM invoices WHERE is_forecast = 1");
    echo "  Deleted forecast invoices\n\n";
    
    // Step 2: Find all actual invoices (non-forecast, non-cancelled)
    echo "Step 2: Finding actual invoices to regenerate forecasts from...\n";
    $invoices = $db->fetchAll("
        SELECT i.*, le.legal_entity_name
        FROM invoices i
        JOIN legal_entities le ON i.legal_entity_id = le.id
        WHERE i.is_forecast = 0
        AND i.invoice_status NOT IN ('cancelled', 'merged')
        AND le.termination_date IS NULL
        ORDER BY i.invoice_date ASC
    ");
    
    echo "Found " . count($invoices) . " actual invoices\n\n";
    
    // Step 3: Regenerate forecasts for each invoice
    echo "Step 3: Regenerating forecasts through to 31/3/31...\n";
    $totalGenerated = 0;
    $errors = [];
    
    foreach ($invoices as $invoice) {
        echo "Processing {$invoice['invoice_number']} (Date: {$invoice['invoice_date']})... ";
        
        try {
            $forecastIds = $autoGenService->generateForecastInvoices($invoice['id']);
            $count = count($forecastIds);
            $totalGenerated += $count;
            
            if ($count > 0) {
                echo "✅ Generated {$count} forecasts\n";
            } else {
                echo "⚠️  No forecasts generated\n";
            }
            
        } catch (Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
            echo $error . "\n";
            $errors[] = [
                'invoice' => $invoice['invoice_number'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Total forecasts generated: {$totalGenerated}\n";
    echo "Errors: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "  - {$error['invoice']}: {$error['error']}\n";
        }
    }
    
    echo "\n✅ Regeneration completed successfully\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n❌ Regeneration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";
    
    exit(1);
}

