<?php
require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

// Check session 10 state
$session = $db->fetchOne("SELECT * FROM xero_import_sessions WHERE id = 10");
$xeroInvoice = $db->fetchOne("SELECT * FROM xero_imported_invoices WHERE session_id = 10");
$systemInvoice = $db->fetchOne("SELECT * FROM invoices WHERE id = 13");

echo "<h1>Session 10 Diagnostic</h1>";

echo "<h2>Session State:</h2>";
echo "<pre>";
echo "ID: " . $session['id'] . "\n";
echo "Status: " . $session['status'] . "\n";
echo "Matched Count: " . $session['matched_count'] . "\n";
echo "Reconciled Count: " . $session['reconciled_count'] . "\n";
echo "</pre>";

echo "<h2>Xero Invoice (FW1056):</h2>";
echo "<pre>";
echo "ID: " . $xeroInvoice['id'] . "\n";
echo "Match Status: " . $xeroInvoice['match_status'] . "\n";
echo "Matched Invoice ID: " . $xeroInvoice['matched_invoice_id'] . "\n";
echo "Is Reconciled: " . ($xeroInvoice['is_reconciled'] ? 'YES' : 'NO') . "\n";
echo "</pre>";

echo "<h2>System Invoice (13):</h2>";
echo "<pre>";
echo "ID: " . $systemInvoice['id'] . "\n";
echo "Invoice Number: " . $systemInvoice['invoice_number'] . "\n";
echo "Invoice Status: " . $systemInvoice['invoice_status'] . "\n";
echo "Xero Invoice ID: " . ($systemInvoice['xero_invoice_id'] ?? 'NULL') . "\n";
echo "</pre>";

echo "<hr>";
echo "<h2>Reset Session 10</h2>";
echo "<p><a href='?action=reset' style='padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px;'>Click to Reset Session 10</a></p>";

if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    // Reset session to reviewing
    $db->query("UPDATE xero_import_sessions SET status = 'reviewing', reconciled_count = 0 WHERE id = 10");

    // Reset Xero invoice
    $db->query("UPDATE xero_imported_invoices SET is_reconciled = FALSE, reconciled_at = NULL WHERE session_id = 10");

    // Reset system invoice
    $db->query("UPDATE invoices SET invoice_status = 'issued', reconciliation_status = 'pending', xero_invoice_id = NULL, xero_invoice_number = NULL WHERE id = 13");

    echo "<h3 style='color: green;'>✅ Session 10 has been reset!</h3>";
    echo "<p><a href='?page=invoices&action=smart_match&session=10'>Go back to Smart Invoice Matching</a></p>";
}

echo "<p><a href='?page=invoices&action=smart_match&session=10'>Go back to Smart Invoice Matching (without resetting)</a></p>";

