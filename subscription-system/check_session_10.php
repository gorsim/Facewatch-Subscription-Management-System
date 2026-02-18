<?php
require_once 'subscription-system/app/Database.php';
use App\Database;

$db = Database::getInstance();

echo "=== SESSION 10 DETAILS ===\n\n";

// Check session details
$session = $db->fetchOne("SELECT * FROM xero_import_sessions WHERE id = 10");
if ($session) {
    echo "Session Name: {$session['session_name']}\n";
    echo "Status: {$session['status']}\n";
    echo "Total Invoices: {$session['total_invoices']}\n";
    echo "Matched Count: {$session['matched_count']}\n";
    echo "Reconciled Count: {$session['reconciled_count']}\n";
    echo "\n";
} else {
    echo "Session 10 not found!\n";
    exit;
}

// Check invoices in this session
echo "=== INVOICES IN SESSION 10 ===\n\n";
$invoices = $db->fetchAll("
    SELECT id, xero_invoice_number, match_status, matched_invoice_id, match_score, is_reconciled
    FROM xero_imported_invoices 
    WHERE session_id = 10
");

if (empty($invoices)) {
    echo "No invoices found in session 10!\n";
} else {
    echo "Found " . count($invoices) . " invoice(s):\n\n";
    foreach ($invoices as $inv) {
        echo "ID: {$inv['id']}\n";
        echo "  Xero Invoice #: {$inv['xero_invoice_number']}\n";
        echo "  Match Status: {$inv['match_status']}\n";
        echo "  Matched Invoice ID: " . ($inv['matched_invoice_id'] ?? 'NULL') . "\n";
        echo "  Match Score: " . ($inv['match_score'] ?? 'NULL') . "\n";
        echo "  Is Reconciled: " . ($inv['is_reconciled'] ? 'YES' : 'NO') . "\n";
        echo "\n";
    }
}

