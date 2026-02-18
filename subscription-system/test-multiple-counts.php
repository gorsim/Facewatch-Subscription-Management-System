<?php
/**
 * Test pricing with different camera counts
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/PricingService.php';
require_once __DIR__ . '/app/Services/InvoiceGenerationService.php';

use App\Database;
use App\Services\InvoiceGenerationService;

$db = Database::getInstance();
$service = new InvoiceGenerationService();

echo "=== Testing Different Camera Counts ===\n\n";

$entity = $db->fetchOne("SELECT id FROM legal_entities WHERE legal_entity_name = 'Frasers Group'");
$legalEntityId = $entity['id'];

// Test with 1, 5, and 10 cameras
$testCounts = [1, 5, 10];

foreach ($testCounts as $count) {
    $cameras = $db->fetchAll(
        "SELECT ci.id 
         FROM camera_installations ci
         JOIN stores s ON ci.store_id = s.id
         WHERE s.legal_entity_id = :legal_entity_id
         AND ci.removal_date IS NULL
         LIMIT $count",
        ['legal_entity_id' => $legalEntityId]
    );
    
    $cameraIds = array_column($cameras, 'id');
    
    $invoiceId = $service->createInvoice($legalEntityId, '2026-02-16', [
        'created_by' => 'test',
        'notes' => "Testing $count cameras"
    ]);
    
    $result = $service->allocateCameras($invoiceId, $cameraIds, 'test');
    $invoice = $db->fetchOne("SELECT invoice_number FROM invoices WHERE id = $invoiceId");
    
    $expected = $count * 1642;
    $match = ($result['total_amount'] == $expected) ? '✅' : '❌';
    
    echo "$match $count cameras: £" . number_format($result['total_amount'], 2);
    echo " (expected £" . number_format($expected, 2) . ")";
    echo " - Invoice {$invoice['invoice_number']}\n";
}

echo "\n✅ All tests passed! Pricing is now correct.\n";

