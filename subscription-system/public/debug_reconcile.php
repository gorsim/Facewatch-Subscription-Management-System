<?php
require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();
$sessionId = 11;

echo "<h1>Debug Reconciliation Issue for Session $sessionId</h1>";

// Check the Xero invoice status
echo "<h2>Xero Invoice Status</h2>";
$xeroInvoice = $db->fetchOne("
    SELECT * FROM xero_imported_invoices 
    WHERE session_id = :session_id
", ['session_id' => $sessionId]);

echo "<pre>";
print_r($xeroInvoice);
echo "</pre>";

// Check what the bulk_reconcile query would find
echo "<h2>What bulk_reconcile Query Would Find</h2>";
$acceptedMatches = $db->fetchAll("
    SELECT * FROM xero_imported_invoices
    WHERE session_id = :session_id
    AND match_status IN ('auto_matched', 'manual_matched')
    AND is_reconciled = FALSE
    AND matched_invoice_id IS NOT NULL
", ['session_id' => $sessionId]);

echo "<p>Found " . count($acceptedMatches) . " matches to reconcile</p>";
echo "<pre>";
print_r($acceptedMatches);
echo "</pre>";

// Check the session counts
echo "<h2>Session Counts</h2>";
$session = $db->fetchOne("
    SELECT * FROM xero_import_sessions 
    WHERE id = :id
", ['id' => $sessionId]);

echo "<pre>";
print_r($session);
echo "</pre>";

// Check the system invoice
echo "<h2>System Invoice Status</h2>";
if ($xeroInvoice && $xeroInvoice['matched_invoice_id']) {
    $systemInvoice = $db->fetchOne("
        SELECT id, invoice_number, invoice_status, reconciliation_status, xero_invoice_id, xero_invoice_number
        FROM invoices
        WHERE id = :id
    ", ['id' => $xeroInvoice['matched_invoice_id']]);

    echo "<pre>";
    print_r($systemInvoice);
    echo "</pre>";

    // Show the problem
    if ($systemInvoice['invoice_status'] === 'reconciled_to_xero') {
        echo "<div style='background: #ffcccc; padding: 15px; border: 2px solid red; margin: 20px 0;'>";
        echo "<h3 style='color: red;'>⚠️ PROBLEM FOUND!</h3>";
        echo "<p><strong>The system invoice is already reconciled!</strong></p>";
        echo "<p>Status: <code>{$systemInvoice['invoice_status']}</code></p>";
        echo "<p>This is why the reconciliation button doesn't work - you can't reconcile an invoice that's already reconciled.</p>";
        echo "<h4>To test the reconciliation button, reset the invoice:</h4>";
        echo "<pre style='background: white; padding: 10px;'>";
        echo "UPDATE invoices \n";
        echo "SET invoice_status = 'pending', \n";
        echo "    reconciliation_status = 'pending', \n";
        echo "    xero_invoice_id = NULL, \n";
        echo "    xero_invoice_number = NULL \n";
        echo "WHERE id = {$systemInvoice['id']};";
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background: #ccffcc; padding: 15px; border: 2px solid green;'>";
        echo "<p>✅ Invoice status is: <code>{$systemInvoice['invoice_status']}</code> - Ready to reconcile!</p>";
        echo "</div>";
    }
}

