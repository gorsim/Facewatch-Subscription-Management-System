<?php
require_once __DIR__ . '/app/Database.php';
use App\Database;

echo "<h1>Audit Log for Session 10</h1>";

try {
    $db = Database::getInstance();
    
    // Get all audit log entries for session 10
    $logs = $db->fetchAll("
        SELECT 
            mal.*,
            xii.xero_invoice_number,
            i.invoice_number as system_invoice_number
        FROM matching_audit_log mal
        LEFT JOIN xero_imported_invoices xii ON mal.xero_invoice_id = xii.id
        LEFT JOIN invoices i ON mal.system_invoice_id = i.id
        WHERE mal.session_id = 10
        ORDER BY mal.created_at DESC
    ");
    
    echo "<h2>Audit Log Entries (" . count($logs) . " total)</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Created At</th>";
    echo "<th>Action</th>";
    echo "<th>Xero Invoice</th>";
    echo "<th>System Invoice</th>";
    echo "<th>Match Score</th>";
    echo "<th>Notes</th>";
    echo "</tr>";
    
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>{$log['id']}</td>";
        echo "<td>{$log['created_at']}</td>";
        echo "<td><strong>{$log['action']}</strong></td>";
        echo "<td>#{$log['xero_invoice_number']} (ID: {$log['xero_invoice_id']})</td>";
        echo "<td>#{$log['system_invoice_number']} (ID: {$log['system_invoice_id']})</td>";
        echo "<td>{$log['match_score']}</td>";
        echo "<td>{$log['notes']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Also check current state of Xero invoice 10
    echo "<h2>Current State of Xero Invoice ID 10</h2>";
    $xeroInvoice = $db->fetchOne("
        SELECT * FROM xero_imported_invoices WHERE id = 10
    ");
    
    echo "<pre>";
    print_r($xeroInvoice);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

