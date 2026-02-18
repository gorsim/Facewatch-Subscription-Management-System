<?php
require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "=== Debugging Session 7 ===\n\n";

// Check session details
$session = $db->fetchOne("SELECT * FROM xero_import_sessions WHERE id = 7");
echo "Session Details:\n";
echo "  ID: " . $session['id'] . "\n";
echo "  Status: " . $session['status'] . "\n";
echo "  Matched Count: " . $session['matched_count'] . "\n";
echo "  Reconciled Count: " . $session['reconciled_count'] . "\n";
echo "  Import Date: " . $session['import_date'] . "\n\n";

// Check xero invoices in this session
$xeroInvoices = $db->fetchAll("
    SELECT *
    FROM xero_imported_invoices
    WHERE session_id = 7
");

echo "Xero Invoices in Session 7:\n";
echo "  Total invoices: " . count($xeroInvoices) . "\n\n";

if (count($xeroInvoices) > 0) {
    echo "  First invoice columns: " . implode(', ', array_keys($xeroInvoices[0])) . "\n\n";

    foreach ($xeroInvoices as $invoice) {
        echo "  Invoice ID: " . $invoice['id'] . "\n";
        echo "    Matched Invoice ID: " . ($invoice['matched_invoice_id'] ?? 'NULL') . "\n";
        echo "    Match Score: " . ($invoice['match_score'] ?? 'NULL') . "\n";
        echo "    Match Status: " . ($invoice['match_status'] ?? 'NULL') . "\n";
        echo "    Match Confidence: " . ($invoice['match_confidence'] ?? 'NULL') . "\n";
        echo "\n";
    }
}

// Check the condition that determines which code path to use
echo "=== Code Path Detection ===\n";
if ($session['status'] === 'reviewing' && $session['matched_count'] == 0) {
    echo "❌ Will run findMatches() - NEW SESSION PATH\n";
    echo "   This is WRONG for historical sessions!\n";
} else {
    echo "✅ Will run getSessionMatches() - HISTORICAL SESSION PATH\n";
}

