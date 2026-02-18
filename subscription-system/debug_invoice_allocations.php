<?php
/**
 * Debug script to check invoice camera allocations
 * Run this to see what's in the database for a specific invoice
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

// Get invoice ID from command line or use default
$invoiceId = $argv[1] ?? null;

if (!$invoiceId) {
    echo "Usage: php debug_invoice_allocations.php <invoice_id>\n";
    echo "Example: php debug_invoice_allocations.php 123\n";
    exit(1);
}

echo "=== DEBUGGING INVOICE #$invoiceId ===\n\n";

// Get invoice details
$invoice = $db->fetchOne(
    "SELECT i.*, le.legal_entity_name 
     FROM invoices i
     JOIN legal_entities le ON i.legal_entity_id = le.id
     WHERE i.id = :id",
    ['id' => $invoiceId]
);

if (!$invoice) {
    echo "❌ Invoice not found!\n";
    exit(1);
}

echo "Invoice: {$invoice['invoice_number']}\n";
echo "Legal Entity: {$invoice['legal_entity_name']}\n";
echo "Invoice Date: {$invoice['invoice_date']}\n";
echo "Invoice Amount: £" . number_format($invoice['invoice_amount'], 2) . "\n";
echo "Expected Amount (stored): £" . number_format($invoice['expected_amount'] ?? 0, 2) . "\n";
echo "Variance (stored): £" . number_format($invoice['variance'] ?? 0, 2) . "\n";
echo "Status: {$invoice['reconciliation_status']}\n\n";

// Get ALL allocation records for this invoice
echo "=== ALL ALLOCATION RECORDS ===\n";
$allocations = $db->fetchAll(
    "SELECT * FROM invoice_camera_allocations WHERE invoice_id = :id ORDER BY id",
    ['id' => $invoiceId]
);

echo "Total records: " . count($allocations) . "\n\n";

$totalSubtotal = 0;
foreach ($allocations as $i => $alloc) {
    echo "Record #" . ($i + 1) . " (ID: {$alloc['id']}):\n";
    echo "  Store ID: " . ($alloc['store_id'] ?? 'NULL') . "\n";
    echo "  Camera Installation ID: " . ($alloc['camera_installation_id'] ?? 'NULL') . "\n";
    echo "  Main Cameras: {$alloc['main_cameras']}\n";
    echo "  Additional Cameras: {$alloc['additional_cameras']}\n";
    echo "  Main Rate: £" . number_format($alloc['main_camera_rate'], 2) . "\n";
    echo "  Additional Rate: £" . number_format($alloc['additional_camera_rate'], 2) . "\n";
    echo "  Rate Applied: £" . number_format($alloc['rate_applied'] ?? 0, 2) . "\n";
    echo "  Subtotal: £" . number_format($alloc['subtotal'], 2) . "\n";
    echo "  Allocation Date: {$alloc['allocation_date']}\n";
    echo "  Created: {$alloc['created_at']}\n";
    echo "\n";
    
    $totalSubtotal += $alloc['subtotal'];
}

echo "=== SUMMARY ===\n";
echo "Sum of all subtotals: £" . number_format($totalSubtotal, 2) . "\n";
echo "Invoice amount: £" . number_format($invoice['invoice_amount'], 2) . "\n";
echo "Difference: £" . number_format($invoice['invoice_amount'] - $totalSubtotal, 2) . "\n";

