<?php
/**
 * Test Auto-Generation Service
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/InvoiceAutoGenerationService.php';
require_once __DIR__ . '/app/Services/InvoiceGenerationService.php';
require_once __DIR__ . '/app/Services/PricingService.php';

use App\Database;
use App\Services\InvoiceAutoGenerationService;

$db = Database::getInstance();

echo "=== Testing Auto-Generation Service ===\n\n";

// Get an existing invoice to test with
$testInvoice = $db->fetchOne("
    SELECT i.*, le.legal_entity_name 
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE i.next_generation_date IS NOT NULL
    ORDER BY i.id DESC
    LIMIT 1
");

if (!$testInvoice) {
    echo "❌ No invoices found with next_generation_date set\n";
    echo "Create an invoice first using the UI\n";
    exit(1);
}

echo "Found test invoice:\n";
echo "  Invoice: {$testInvoice['invoice_number']}\n";
echo "  Legal Entity: {$testInvoice['legal_entity_name']}\n";
echo "  Next Generation Date: {$testInvoice['next_generation_date']}\n";
echo "  Current Status: {$testInvoice['invoice_status']}\n\n";

// Test 1: Check what would be generated today
echo "Test 1: Check invoices due for generation today\n";
$service = new InvoiceAutoGenerationService();
$results = $service->runAutoGeneration();

echo "Results:\n";
echo "  Checked: {$results['checked']} invoices\n";
echo "  Generated: {$results['generated']} new invoices\n";
echo "  Skipped: {$results['skipped']}\n";
echo "  Errors: {$results['errors']}\n\n";

if ($results['generated'] > 0) {
    echo "✅ Successfully generated {$results['generated']} invoice(s)!\n\n";
    echo "Details:\n";
    foreach ($results['invoices'] as $invoice) {
        if ($invoice['status'] === 'generated') {
            echo "  - Parent: {$invoice['parent_number']}\n";
            echo "    New Invoice ID: {$invoice['new_id']}\n";
            
            // Get details of new invoice
            $newInvoice = $db->fetchOne("SELECT * FROM invoices WHERE id = :id", ['id' => $invoice['new_id']]);
            echo "    New Invoice Number: {$newInvoice['invoice_number']}\n";
            echo "    Amount: £" . number_format($newInvoice['invoice_amount'], 2) . "\n";
            echo "    Status: {$newInvoice['invoice_status']}\n";
            echo "    Is Auto-Generated: " . ($newInvoice['is_auto_generated'] ? 'Yes' : 'No') . "\n\n";
        }
    }
} else {
    echo "ℹ️  No invoices generated (none due today)\n\n";
}

// Test 2: Simulate future date
echo "Test 2: Simulate generation on next_generation_date\n";
$futureDate = $testInvoice['next_generation_date'];
echo "  Simulating date: $futureDate\n";

$results = $service->runAutoGeneration($futureDate);

echo "Results:\n";
echo "  Checked: {$results['checked']} invoices\n";
echo "  Generated: {$results['generated']} new invoices\n";
echo "  Skipped: {$results['skipped']}\n";
echo "  Errors: {$results['errors']}\n\n";

if ($results['generated'] > 0) {
    echo "✅ Successfully generated {$results['generated']} invoice(s) for future date!\n";
} else if ($results['skipped'] > 0) {
    echo "ℹ️  Invoice already generated for this period\n";
} else {
    echo "ℹ️  No invoices generated\n";
}

echo "\n✅ Auto-generation tests complete!\n";

