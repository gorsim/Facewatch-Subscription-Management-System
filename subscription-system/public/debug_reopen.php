<?php
require_once __DIR__ . '/../app/Database.php';
use App\Database;

echo "<h1>Debug Re-Open Issue for Session 10</h1>";

try {
    $db = Database::getInstance();
    
    // Check current state of Xero invoice
    echo "<h2>Current State of Xero Invoice</h2>";
    $xeroInvoice = $db->query("
        SELECT
            id,
            xero_invoice_id,
            contact_name,
            xero_invoice_number,
            amount,
            matched_invoice_id,
            match_status,
            is_reconciled,
            reconciled_at
        FROM xero_imported_invoices
        WHERE session_id = 10
    ")->fetch();
    
    echo "<pre>";
    print_r($xeroInvoice);
    echo "</pre>";
    
    // Check if the system invoice still exists
    echo "<h2>System Invoice Status</h2>";
    if ($xeroInvoice && $xeroInvoice['matched_invoice_id']) {
        $systemInvoice = $db->query("
            SELECT
                id,
                invoice_number,
                legal_entity_id,
                invoice_amount,
                reconciliation_status,
                xero_invoice_id,
                xero_invoice_number
            FROM invoices
            WHERE id = :id
        ", ['id' => $xeroInvoice['matched_invoice_id']])->fetch();
        
        if ($systemInvoice) {
            echo "<p style='color: green;'>✓ System invoice EXISTS</p>";
            echo "<pre>";
            print_r($systemInvoice);
            echo "</pre>";
        } else {
            echo "<p style='color: red;'>✗ System invoice DOES NOT EXIST (was deleted!)</p>";
            echo "<p>This explains why matched_invoice_id is NULL - the foreign key constraint ON DELETE SET NULL was triggered.</p>";
        }
    } else {
        echo "<p>No matched_invoice_id to check</p>";
    }
    
    // Check audit log for delete actions
    echo "<h2>Audit Log - Looking for Delete Actions</h2>";
    $deleteActions = $db->query("
        SELECT
            action,
            xero_invoice_id,
            system_invoice_id,
            match_details,
            performed_at
        FROM matching_audit_log
        WHERE session_id = 10
        AND action = 'deleted'
        ORDER BY performed_at DESC
    ")->fetchAll();
    
    if ($deleteActions) {
        echo "<p style='color: red;'>Found " . count($deleteActions) . " delete action(s):</p>";
        echo "<pre>";
        print_r($deleteActions);
        echo "</pre>";
    } else {
        echo "<p style='color: green;'>No delete actions found in audit log</p>";
    }
    
    // Show complete audit log
    echo "<h2>Complete Audit Log for Session 10</h2>";
    $auditLog = $db->query("
        SELECT
            action,
            xero_invoice_id,
            system_invoice_id,
            match_details,
            performed_at
        FROM matching_audit_log
        WHERE session_id = 10
        ORDER BY performed_at ASC
    ")->fetchAll();

    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Time</th><th>Action</th><th>Xero ID</th><th>System ID</th><th>Details</th></tr>";
    foreach ($auditLog as $entry) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($entry['performed_at']) . "</td>";
        echo "<td>" . htmlspecialchars($entry['action']) . "</td>";
        echo "<td>" . htmlspecialchars($entry['xero_invoice_id'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($entry['system_invoice_id'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($entry['match_details'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

