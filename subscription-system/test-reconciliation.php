<?php
/**
 * Test Reconciliation Workflow
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/InvoiceStatusService.php';

use App\Database;
use App\Services\InvoiceStatusService;

$db = Database::getInstance();
$statusService = new InvoiceStatusService();

echo "=== Testing Reconciliation Workflow ===\n\n";

// Get a test invoice
$invoice = $db->fetchOne("
    SELECT * FROM invoices 
    WHERE invoice_status = 'draft'
    ORDER BY id DESC 
    LIMIT 1
");

if (!$invoice) {
    echo "❌ No draft invoices found. Creating one...\n";
    exit(1);
}

echo "Found test invoice:\n";
echo "  ID: {$invoice['id']}\n";
echo "  Number: {$invoice['invoice_number']}\n";
echo "  Status: {$invoice['invoice_status']}\n\n";

// Step 1: Mark as Issued
echo "Step 1: Marking invoice as Issued...\n";
try {
    $statusService->changeStatus(
        $invoice['id'],
        'issued',
        'test_user@example.com',
        'Testing reconciliation workflow'
    );
    echo "✅ Status changed to: issued\n\n";
} catch (Exception $e) {
    echo "❌ Failed to mark as issued: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Reconcile to Xero
echo "Step 2: Reconciling to Xero...\n";
$testXeroId = 'test-xero-id-' . time();
$testXeroNumber = 'XERO-TEST-' . date('Y-m-d');

try {
    $statusService->reconcileToXero(
        $invoice['id'],
        $testXeroId,
        $testXeroNumber,
        'test_user@example.com'
    );
    echo "✅ Reconciled to Xero!\n";
    echo "   Xero ID: $testXeroId\n";
    echo "   Xero Number: $testXeroNumber\n\n";
} catch (Exception $e) {
    echo "❌ Failed to reconcile: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 3: Verify final state
echo "Step 3: Verifying final state...\n";
$updatedInvoice = $db->fetchOne("SELECT * FROM invoices WHERE id = :id", ['id' => $invoice['id']]);

echo "Final invoice state:\n";
echo "  Status: {$updatedInvoice['invoice_status']}\n";
echo "  Xero Invoice ID: {$updatedInvoice['xero_invoice_id']}\n";
echo "  Xero Invoice Number: {$updatedInvoice['xero_invoice_number']}\n";
echo "  Reconciled Date: {$updatedInvoice['reconciled_date']}\n";
echo "  Reconciled By: {$updatedInvoice['reconciled_by']}\n\n";

// Step 4: Check status history
echo "Step 4: Checking status history...\n";
$history = $statusService->getStatusHistory($invoice['id']);

echo "Status change history:\n";
foreach ($history as $h) {
    echo "  - {$h['changed_at']}: {$h['old_status']} → {$h['new_status']} by {$h['changed_by']}\n";
    if ($h['notes']) {
        echo "    Notes: {$h['notes']}\n";
    }
}

echo "\n✅ Reconciliation workflow test complete!\n";
echo "\nYou can now view this invoice in the UI:\n";
echo "http://localhost:8888/subscription-system/public/?page=invoices&action=view&id={$invoice['id']}\n";

