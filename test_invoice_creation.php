<?php
/**
 * Test Invoice Creation
 * Creates a test invoice for Frasers Group
 */

require_once __DIR__ . '/subscription-system/app/Database.php';
require_once __DIR__ . '/subscription-system/app/Services/InvoiceGenerationService.php';
require_once __DIR__ . '/subscription-system/app/Services/PricingService.php';

use App\Database;
use App\Services\InvoiceGenerationService;

$db = Database::getInstance();
$invoiceService = new InvoiceGenerationService();

echo "=== INVOICE CREATION TEST ===\n\n";

// Test with Frasers Group (ID: 10)
$legalEntityId = 10;
$invoiceDate = date('Y-m-d'); // Today

try {
    // Get legal entity details
    $entity = $db->fetchOne(
        "SELECT * FROM legal_entities WHERE id = :id",
        ['id' => $legalEntityId]
    );
    
    echo "Legal Entity: {$entity['legal_entity_name']}\n";
    echo "Payment Frequency: {$entity['payment_frequency']}\n";
    echo "Pricing Model: {$entity['pricing_model']}\n\n";
    
    // Get all active cameras for this entity
    $cameras = $db->fetchAll("
        SELECT
            ci.*,
            s.store_name,
            s.id as store_id
        FROM camera_installations ci
        JOIN stores s ON ci.store_id = s.id
        WHERE s.legal_entity_id = :legal_entity_id
        AND ci.removal_date IS NULL
        ORDER BY s.store_name, ci.id
    ", ['legal_entity_id' => $legalEntityId]);
    
    $cameraCount = count($cameras);
    echo "Active Cameras Found: {$cameraCount}\n\n";
    
    if ($cameraCount === 0) {
        echo "❌ No cameras found for this legal entity!\n";
        exit(1);
    }
    
    // Step 1: Create the invoice
    echo "Step 1: Creating invoice...\n";
    $invoiceId = $invoiceService->createInvoice(
        $legalEntityId,
        $invoiceDate,
        [
            'status' => 'draft',
            'created_by' => 'test_script',
            'notes' => 'Test invoice created by automated script'
        ]
    );
    
    echo "✅ Invoice created! ID: {$invoiceId}\n\n";
    
    // Get invoice details
    $invoice = $db->fetchOne(
        "SELECT * FROM invoices WHERE id = :id",
        ['id' => $invoiceId]
    );
    
    echo "Invoice Number: {$invoice['invoice_number']}\n";
    echo "Invoice Date: {$invoice['invoice_date']}\n";
    echo "Status: {$invoice['invoice_status']}\n";
    echo "Next Generation Date: {$invoice['next_generation_date']}\n\n";
    
    // Step 2: Allocate cameras
    echo "Step 2: Allocating {$cameraCount} cameras...\n";
    $cameraIds = array_column($cameras, 'id');
    
    $result = $invoiceService->allocateCameras(
        $invoiceId,
        $cameraIds,
        'test_script'
    );
    
    echo "✅ Cameras allocated!\n";
    echo "Camera Count: {$result['camera_count']}\n";
    echo "Total Amount: £" . number_format($result['total_amount'], 2) . "\n\n";
    
    // Step 3: Get allocation details
    echo "Step 3: Viewing allocation details...\n\n";
    $allocations = $invoiceService->getInvoiceAllocations($invoiceId);
    
    // Group by store
    $byStore = [];
    foreach ($allocations as $allocation) {
        $storeName = $allocation['store_name'];
        if (!isset($byStore[$storeName])) {
            $byStore[$storeName] = [];
        }
        $byStore[$storeName][] = $allocation;
    }
    
    foreach ($byStore as $storeName => $storeAllocations) {
        echo "📍 {$storeName} (" . count($storeAllocations) . " cameras)\n";
        $storeTotal = 0;
        foreach ($storeAllocations as $allocation) {
            $cameraId = $allocation['camera_installation_id'];
            echo "   - Camera #{$cameraId} ({$allocation['camera_type']}): £" . number_format($allocation['price_charged'], 2);
            echo " (Tier: {$allocation['pricing_tier']})\n";
            $storeTotal += $allocation['price_charged'];
        }
        echo "   Subtotal: £" . number_format($storeTotal, 2) . "\n\n";
    }
    
    // Step 4: Check generation log
    echo "Step 4: Checking audit trail...\n";
    $log = $db->fetchOne(
        "SELECT * FROM invoice_generation_log WHERE invoice_id = :id ORDER BY generation_date DESC LIMIT 1",
        ['id' => $invoiceId]
    );
    
    if ($log) {
        echo "✅ Generation logged:\n";
        echo "   Type: {$log['generation_type']}\n";
        echo "   Triggered by: {$log['triggered_by']}\n";
        echo "   Camera count: {$log['camera_count']}\n";
        echo "   Total amount: £" . number_format($log['total_amount'], 2) . "\n";
    }
    
    echo "\n=== TEST COMPLETE ===\n";
    echo "✅ Invoice {$invoice['invoice_number']} created successfully!\n";
    echo "✅ {$result['camera_count']} cameras allocated\n";
    echo "✅ Total: £" . number_format($result['total_amount'], 2) . "\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

