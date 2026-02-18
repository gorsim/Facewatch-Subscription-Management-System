<?php
/**
 * Test Camera Allocation Service with New PricingService
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/PricingService.php';
require_once __DIR__ . '/app/Services/CameraAllocationService.php';

use App\Database;
use App\Services\CameraAllocationService;

echo "<h1>🧪 Testing Camera Allocation Service</h1>\n";
echo "<p style='color: #666;'>Testing the updated allocation service with new PricingService</p>\n";

try {
    $db = Database::getInstance();
    $allocationService = new CameraAllocationService();
    
    // Find an invoice to test with
    $invoice = $db->fetchOne("
        SELECT i.*, le.legal_entity_name
        FROM invoices i
        JOIN legal_entities le ON i.legal_entity_id = le.id
        ORDER BY i.invoice_date DESC
        LIMIT 1
    ");
    
    if (!$invoice) {
        echo "<p style='color: red;'>❌ No invoices found in database. Please create an invoice first.</p>\n";
        exit;
    }
    
    echo "<h2>Testing with Invoice #{$invoice['id']}</h2>\n";
    echo "<table border='1' cellpadding='5'>
        <tr><th>Property</th><th>Value</th></tr>
        <tr><td>Invoice ID</td><td>{$invoice['id']}</td></tr>
        <tr><td>Legal Entity</td><td>{$invoice['legal_entity_name']}</td></tr>
        <tr><td>Invoice Date</td><td>{$invoice['invoice_date']}</td></tr>
        <tr><td>Invoice Amount</td><td>£" . number_format($invoice['invoice_amount'], 2) . "</td></tr>
    </table>\n";
    
    echo "<hr>\n";
    echo "<h2>Running Camera Allocation...</h2>\n";
    
    // Run the allocation
    $result = $allocationService->allocateCamerasToInvoice($invoice['id']);
    
    if (isset($result['error'])) {
        echo "<p style='color: red;'>❌ Error: {$result['error']}</p>\n";
        exit;
    }
    
    echo "<h3>✅ Allocation Complete!</h3>\n";
    
    // Display summary
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; margin-top: 20px;'>
        <tr style='background: #f8f9fa;'>
            <th colspan='2'>Allocation Summary</th>
        </tr>
        <tr><td><strong>Legal Entity</strong></td><td>{$result['legal_entity_name']}</td></tr>
        <tr><td><strong>Allocation Date</strong></td><td>{$result['allocation_date']}</td></tr>
        <tr><td><strong>Total Cameras</strong></td><td>{$result['total_cameras']}</td></tr>
        <tr><td><strong>Pricing Type</strong></td><td>" . ucfirst($result['pricing_type']) . "</td></tr>
        <tr><td><strong>Pricing Tier</strong></td><td>{$result['pricing_tier']}</td></tr>
        <tr><td><strong>Rate per Camera</strong></td><td>£" . number_format($result['rate_per_camera'], 2) . "</td></tr>
        <tr><td><strong>Stores Allocated</strong></td><td>{$result['stores_count']}</td></tr>
        <tr style='background: #d4edda;'>
            <td><strong>Total Allocated Amount</strong></td>
            <td><strong>£" . number_format($result['total_amount'], 2) . "</strong></td>
        </tr>
    </table>\n";
    
    // Display per-store breakdown
    echo "<hr>\n";
    echo "<h3>Per-Store Breakdown</h3>\n";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>
        <thead style='background: #f8f9fa;'>
            <tr>
                <th>Store ID</th>
                <th>Main Cameras</th>
                <th>Additional Cameras</th>
                <th>Total Cameras</th>
                <th>Rate per Camera</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>\n";
    
    foreach ($result['allocations'] as $allocation) {
        echo "<tr>
            <td>{$allocation['store_id']}</td>
            <td style='text-align: center;'>{$allocation['main_cameras']}</td>
            <td style='text-align: center;'>{$allocation['additional_cameras']}</td>
            <td style='text-align: center;'><strong>{$allocation['total_cameras']}</strong></td>
            <td>£" . number_format($allocation['rate_per_camera'], 2) . "</td>
            <td><strong>£" . number_format($allocation['subtotal'], 2) . "</strong></td>
        </tr>\n";
    }
    
    echo "</tbody>
    </table>\n";
    
    // Compare with invoice amount
    echo "<hr>\n";
    echo "<h3>Invoice Reconciliation</h3>\n";
    
    $invoiceAmount = (float) $invoice['invoice_amount'];
    $allocatedAmount = (float) $result['total_amount'];
    $difference = $invoiceAmount - $allocatedAmount;
    $percentDiff = $invoiceAmount > 0 ? ($difference / $invoiceAmount) * 100 : 0;
    
    $statusColor = abs($difference) < 0.01 ? '#28a745' : '#dc3545';
    $statusIcon = abs($difference) < 0.01 ? '✅' : '⚠️';
    
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>
        <tr><td><strong>Invoice Amount</strong></td><td>£" . number_format($invoiceAmount, 2) . "</td></tr>
        <tr><td><strong>Allocated Amount</strong></td><td>£" . number_format($allocatedAmount, 2) . "</td></tr>
        <tr style='background: " . ($difference >= 0 ? '#fff3cd' : '#f8d7da') . ";'>
            <td><strong>Difference</strong></td>
            <td><strong style='color: {$statusColor};'>{$statusIcon} £" . number_format(abs($difference), 2) . " (" . number_format(abs($percentDiff), 2) . "%)</strong></td>
        </tr>
    </table>\n";
    
    if (abs($difference) < 0.01) {
        echo "<p style='color: #28a745; font-weight: bold;'>✅ Perfect match! Invoice amount equals allocated amount.</p>\n";
    } else {
        echo "<p style='color: #dc3545;'>⚠️ There is a difference between the invoice amount and allocated amount.</p>\n";
        echo "<p style='color: #666;'>This is expected if the invoice was created before the new pricing tiers were implemented.</p>\n";
    }
    
    echo "<hr>\n";
    echo "<h2 style='color: green;'>✅ Test Complete!</h2>\n";
    echo "<p><a href='?page=invoices&action=view&id={$invoice['id']}'>View Invoice Details →</a></p>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Test Failed!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

