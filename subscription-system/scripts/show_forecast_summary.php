<?php
/**
 * Show a summary of forecast invoices for the dashboard
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "=== Forecast Invoice Dashboard Summary ===\n\n";

// Get counts by status
$statusCounts = $db->fetchAll("
    SELECT 
        CASE 
            WHEN is_forecast = 1 THEN 'Forecast'
            ELSE invoice_status
        END as status,
        COUNT(*) as count
    FROM invoices
    GROUP BY status
    ORDER BY status
");

echo "Invoice Counts by Status:\n";
foreach ($statusCounts as $row) {
    echo "  " . ucfirst($row['status']) . ": " . $row['count'] . "\n";
}

// Get upcoming forecast conversions (next 30 days)
echo "\n=== Upcoming Forecast Conversions (Next 30 Days) ===\n";
$upcoming = $db->fetchAll("
    SELECT 
        i.invoice_number,
        i.invoice_date,
        le.legal_entity_name,
        i.invoice_amount
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE i.is_forecast = 1
    AND i.invoice_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY i.invoice_date
    LIMIT 20
");

if (empty($upcoming)) {
    echo "No forecast invoices due for conversion in the next 30 days.\n";
} else {
    echo "Found " . count($upcoming) . " forecast invoices due for conversion:\n\n";
    foreach ($upcoming as $inv) {
        echo "  " . $inv['invoice_number'] . " - " . $inv['invoice_date'] . " - " . 
             $inv['legal_entity_name'] . " - £" . number_format($inv['invoice_amount'], 2) . "\n";
    }
}

// Sample of forecast invoices by year
echo "\n=== Forecast Invoices by Year ===\n";
$byYear = $db->fetchAll("
    SELECT 
        YEAR(invoice_date) as year,
        COUNT(*) as count,
        SUM(invoice_amount) as total_amount
    FROM invoices
    WHERE is_forecast = 1
    GROUP BY YEAR(invoice_date)
    ORDER BY year
");

foreach ($byYear as $row) {
    echo "  " . $row['year'] . ": " . $row['count'] . " invoices, £" . 
         number_format($row['total_amount'], 2) . " total\n";
}

// Show a few example chains
echo "\n=== Sample Invoice Chains (First 5 invoices) ===\n";
$sampleParents = $db->fetchAll("
    SELECT id, invoice_number, invoice_date, legal_entity_id
    FROM invoices
    WHERE is_forecast = 0
    AND invoice_status NOT IN ('cancelled', 'merged')
    ORDER BY invoice_date DESC
    LIMIT 3
");

foreach ($sampleParents as $parent) {
    echo "\n" . $parent['invoice_number'] . " (" . $parent['invoice_date'] . "):\n";
    
    $children = $db->fetchAll("
        SELECT invoice_number, invoice_date
        FROM invoices
        WHERE parent_invoice_id = :parent_id
        ORDER BY invoice_date
        LIMIT 5
    ", ['parent_id' => $parent['id']]);
    
    if (empty($children)) {
        echo "  No forecast invoices\n";
    } else {
        foreach ($children as $child) {
            echo "  → " . $child['invoice_number'] . " (" . $child['invoice_date'] . ")\n";
        }
        if (count($children) == 5) {
            echo "  ... and more\n";
        }
    }
}

echo "\n✅ Dashboard summary complete\n";

