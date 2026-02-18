<?php
/**
 * Test the pricing fix - verify cameras are charged £1,642 each, not divided
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/PricingService.php';
require_once __DIR__ . '/app/Services/InvoiceGenerationService.php';

use App\Database;
use App\Services\InvoiceGenerationService;

$db = Database::getInstance();
$service = new InvoiceGenerationService();

echo "=== Testing Fixed Pricing Calculation ===\n\n";

// Get Frasers Group
$entity = $db->fetchOne("SELECT id FROM legal_entities WHERE legal_entity_name = 'Frasers Group'");
$legalEntityId = $entity['id'];

// Get 3 active cameras
$cameras = $db->fetchAll(
    "SELECT ci.id 
     FROM camera_installations ci
     JOIN stores s ON ci.store_id = s.id
     WHERE s.legal_entity_id = :legal_entity_id
     AND ci.removal_date IS NULL
     LIMIT 3",
    ['legal_entity_id' => $legalEntityId]
);

$cameraIds = array_column($cameras, 'id');
echo "Testing with " . count($cameraIds) . " cameras\n";
echo "Camera IDs: " . implode(', ', $cameraIds) . "\n\n";

// Create invoice
$invoiceId = $service->createInvoice($legalEntityId, '2026-02-16', [
    'created_by' => 'test_script',
    'notes' => 'Testing fixed pricing calculation'
]);

echo "Created invoice ID: $invoiceId\n\n";

// Allocate cameras
$result = $service->allocateCameras($invoiceId, $cameraIds, 'test_script');

echo "Allocation Results:\n";
echo "  Camera Count: {$result['camera_count']}\n";
echo "  Total Amount: £" . number_format($result['total_amount'], 2) . "\n";
echo "  Expected (3 × £1,642): £" . number_format(3 * 1642, 2) . "\n\n";

echo "Individual Camera Prices:\n";
foreach ($result['allocations'] as $alloc) {
    echo "  Camera {$alloc['camera_id']}: £" . number_format($alloc['price_charged'], 2) . " ({$alloc['tier']})\n";
}

// Get invoice number
$invoice = $db->fetchOne("SELECT invoice_number FROM invoices WHERE id = $invoiceId");
echo "\n";

if ($result['total_amount'] == 3 * 1642) {
    echo "✅ SUCCESS! Invoice {$invoice['invoice_number']} has correct pricing!\n";
    echo "   Each camera charged £1,642.00 as expected.\n";
} else {
    echo "❌ FAILED! Invoice {$invoice['invoice_number']} has incorrect pricing!\n";
    echo "   Expected: £" . number_format(3 * 1642, 2) . "\n";
    echo "   Got: £" . number_format($result['total_amount'], 2) . "\n";
}

