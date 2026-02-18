<?php
/**
 * Fix INV-135 Chain - Delete incorrectly generated invoices and regenerate forecasts
 * 
 * Problem: All child invoices of INV-135 were created as actual invoices with the same date
 * Solution: Delete them and regenerate as proper forecast invoices
 */

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/InvoiceAutoGenerationService.php';
require_once __DIR__ . '/../app/Services/InvoiceGenerationService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';

use App\Database;
use App\Services\InvoiceAutoGenerationService;

echo "=== Fix INV-135 Chain ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance();
    $autoGenService = new InvoiceAutoGenerationService($db);
    
    // Step 1: Find INV-135
    $inv135 = $db->fetchOne("SELECT id, invoice_number FROM invoices WHERE invoice_number = 'INV-135'");
    
    if (!$inv135) {
        echo "❌ INV-135 not found!\n";
        exit(1);
    }
    
    echo "Found INV-135 (ID: {$inv135['id']})\n\n";
    
    // Step 2: Find all child invoices (INV-2085 onwards)
    $children = $db->fetchAll("
        SELECT id, invoice_number, invoice_date, is_forecast, invoice_status
        FROM invoices 
        WHERE parent_invoice_id = :parent_id
        ORDER BY id
    ", ['parent_id' => $inv135['id']]);
    
    echo "Found " . count($children) . " child invoices\n";
    
    if (count($children) > 0) {
        echo "\nFirst few:\n";
        foreach (array_slice($children, 0, 5) as $child) {
            $type = $child['is_forecast'] ? 'FORECAST' : 'ACTUAL';
            echo "  {$child['invoice_number']}: {$child['invoice_date']} - {$type}\n";
        }
        
        // Step 3: Delete all child invoices
        echo "\nStep 3: Deleting all child invoices...\n";
        
        foreach ($children as $child) {
            // Delete camera allocations first
            $db->delete('invoice_camera_allocations', 'invoice_id = :id', ['id' => $child['id']]);

            // Delete generation log entries
            $db->delete('invoice_generation_log', 'invoice_id = :id', ['id' => $child['id']]);

            // Delete the invoice
            $db->delete('invoices', 'id = :id', ['id' => $child['id']]);
        }
        
        echo "✅ Deleted " . count($children) . " invoices\n\n";
    }
    
    // Step 4: Regenerate forecasts
    echo "Step 4: Regenerating forecast invoices...\n";
    
    $forecastIds = $autoGenService->generateForecastInvoices($inv135['id']);
    
    echo "✅ Generated " . count($forecastIds) . " forecast invoices\n\n";
    
    // Step 5: Verify the results
    echo "Step 5: Verifying results...\n";
    
    $newChildren = $db->fetchAll("
        SELECT invoice_number, invoice_date, is_forecast, invoice_status
        FROM invoices 
        WHERE parent_invoice_id = :parent_id
        ORDER BY invoice_date
        LIMIT 10
    ", ['parent_id' => $inv135['id']]);
    
    echo "First 10 new forecast invoices:\n";
    foreach ($newChildren as $child) {
        $type = $child['is_forecast'] ? 'FORECAST' : 'ACTUAL';
        echo "  {$child['invoice_number']}: {$child['invoice_date']} - {$type} - {$child['invoice_status']}\n";
    }
    
    echo "\n✅ Fix completed successfully!\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n❌ Fix failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";
    
    exit(1);
}

