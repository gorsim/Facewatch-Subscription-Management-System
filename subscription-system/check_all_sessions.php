<?php
require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();
$conn = $db->getConnection();

echo "=== All Import Sessions ===\n\n";

$stmt = $conn->query("
    SELECT id, session_name, status, total_invoices, matched_count, reconciled_count, created_at
    FROM xero_import_sessions
    ORDER BY created_at DESC
");
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sessions as $session) {
    echo "Session {$session['id']}: {$session['session_name']}\n";
    echo "  Status: {$session['status']}\n";
    echo "  Total: {$session['total_invoices']}\n";
    echo "  Matched: {$session['matched_count']}\n";
    echo "  Reconciled: {$session['reconciled_count']}\n";
    echo "  Created: {$session['created_at']}\n";

    // Check actual invoice counts
    $stmt2 = $conn->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN matched_invoice_id IS NOT NULL THEN 1 ELSE 0 END) as matched,
            SUM(CASE WHEN is_reconciled = 1 THEN 1 ELSE 0 END) as reconciled
        FROM xero_imported_invoices
        WHERE session_id = :session_id
    ");
    $stmt2->execute(['session_id' => $session['id']]);
    $invoices = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo "  Actual counts: {$invoices[0]['total']} total, {$invoices[0]['matched']} matched, {$invoices[0]['reconciled']} reconciled\n";
    echo "\n";
}

