<?php
require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();
$conn = $db->getConnection();

echo "=== Fixing Session Match Counts ===\n\n";

// Update match_score from match_confidence for all existing records
echo "1. Updating match_score from match_confidence...\n";
$conn->exec("
    UPDATE xero_imported_invoices
    SET match_score = match_confidence
    WHERE match_confidence IS NOT NULL AND match_score IS NULL
");
echo "   ✅ Done\n\n";

// Update matched_count for all sessions based on actual matches
echo "2. Updating matched_count for all sessions...\n";
$conn->exec("
    UPDATE xero_import_sessions s
    SET matched_count = (
        SELECT COUNT(*)
        FROM xero_imported_invoices xii
        WHERE xii.session_id = s.id
        AND xii.matched_invoice_id IS NOT NULL
    )
");
echo "   ✅ Done\n\n";

// Update reconciled_count for all sessions based on actual reconciliations
echo "3. Updating reconciled_count for all sessions...\n";
$conn->exec("
    UPDATE xero_import_sessions s
    SET reconciled_count = (
        SELECT COUNT(*)
        FROM xero_imported_invoices xii
        WHERE xii.session_id = s.id
        AND xii.is_reconciled = 1
    )
");
echo "   ✅ Done\n\n";

// Update status to 'completed' for sessions that have been fully processed
echo "4. Updating status to 'completed' for processed sessions...\n";
// Mark as completed if:
// - Has matches/reconciliations, OR
// - All invoices have been processed (no 'unmatched' status remaining)
$conn->exec("
    UPDATE xero_import_sessions s
    SET status = 'completed'
    WHERE status = 'reviewing'
    AND (
        matched_count > 0
        OR reconciled_count > 0
        OR NOT EXISTS (
            SELECT 1
            FROM xero_imported_invoices xii
            WHERE xii.session_id = s.id
            AND xii.match_status = 'unmatched'
        )
    )
");
echo "   ✅ Done\n\n";

// Show updated session 7
$session = $db->fetchOne("SELECT * FROM xero_import_sessions WHERE id = 7");
echo "=== Updated Session 7 ===\n";
echo "  Status: " . $session['status'] . "\n";
echo "  Matched Count: " . $session['matched_count'] . "\n";
echo "  Reconciled Count: " . $session['reconciled_count'] . "\n\n";

$xeroInvoice = $db->fetchOne("SELECT * FROM xero_imported_invoices WHERE session_id = 7");
echo "=== Updated Xero Invoice ===\n";
echo "  Match Score: " . ($xeroInvoice['match_score'] ?? 'NULL') . "\n";
echo "  Match Confidence: " . ($xeroInvoice['match_confidence'] ?? 'NULL') . "\n";
echo "  Matched Invoice ID: " . ($xeroInvoice['matched_invoice_id'] ?? 'NULL') . "\n";
echo "  Match Status: " . ($xeroInvoice['match_status'] ?? 'NULL') . "\n\n";

echo "✅ All sessions fixed!\n";

