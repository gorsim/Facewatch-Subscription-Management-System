<?php
/**
 * Check why INV-001, INV-002, INV-003 have zero amounts
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "=== Checking Zero-Amount Invoices ===\n\n";

// Check invoices and their camera allocations
$invoices = $db->fetchAll("
    SELECT 
        i.id,
        i.invoice_number,
        i.invoice_amount,
        i.invoice_status,
        i.legal_entity_id,
        i.invoice_date,
        le.entity_name,
        COUNT(ica.id) as camera_count,
        SUM(ica.price_charged) as total_from_allocations
    FROM invoices i
    LEFT JOIN legal_entities le ON i.legal_entity_id = le.id
    LEFT JOIN invoice_camera_allocations ica ON i.id = ica.invoice_id
    WHERE i.invoice_number IN ('INV-001', 'INV-002', 'INV-003', 'INV-006')
    GROUP BY i.id
    ORDER BY i.id
");

echo "Invoice Details:\n";
echo str_repeat("-", 120) . "\n";
printf("%-4s %-15s %-20s %-15s %-12s %-12s %-20s\n", 
    "ID", "Invoice #", "Entity", "Amount", "Status", "Cameras", "Allocation Total");
echo str_repeat("-", 120) . "\n";

foreach ($invoices as $inv) {
    printf("%-4s %-15s %-20s £%-14s %-12s %-12s £%-19s\n",
        $inv['id'],
        $inv['invoice_number'],
        substr($inv['entity_name'], 0, 20),
        number_format($inv['invoice_amount'], 2),
        $inv['invoice_status'],
        $inv['camera_count'],
        $inv['total_from_allocations'] ? number_format($inv['total_from_allocations'], 2) : '0.00'
    );
}

echo "\n\n=== Checking Available Cameras for Zero-Amount Invoices ===\n\n";

// For each zero-amount invoice, check if there are available cameras
foreach ($invoices as $inv) {
    if ($inv['invoice_amount'] == 0) {
        echo "Invoice {$inv['invoice_number']} (ID: {$inv['id']}):\n";
        echo "  Entity: {$inv['entity_name']}\n";
        echo "  Date: {$inv['invoice_date']}\n";
        
        // Check available cameras for this entity
        $cameras = $db->fetchAll("
            SELECT ci.id, ci.camera_name, s.store_name
            FROM camera_installations ci
            JOIN stores s ON ci.store_id = s.id
            WHERE s.legal_entity_id = :legal_entity_id
            AND ci.removal_date IS NULL
        ", ['legal_entity_id' => $inv['legal_entity_id']]);
        
        echo "  Available cameras: " . count($cameras) . "\n";
        if (count($cameras) > 0) {
            echo "  Camera IDs: " . implode(', ', array_column($cameras, 'id')) . "\n";
        }
        echo "\n";
    }
}

echo "\n=== Recommendation ===\n";
echo "Invoices with £0.00 amounts need to have cameras allocated to them.\n";
echo "This can be done by:\n";
echo "1. Using the invoice edit page to allocate cameras\n";
echo "2. Running a script to allocate cameras programmatically\n";
echo "3. Deleting these invoices and recreating them with proper camera allocation\n";

