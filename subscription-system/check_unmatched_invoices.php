<?php
require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();
$conn = $db->getConnection();

echo "=== Checking Unmatched Invoices in 'Reviewing' Sessions ===\n\n";

$sessions = [1, 3, 4, 5, 6];

foreach ($sessions as $sessionId) {
    echo "Session $sessionId:\n";
    
    $stmt = $conn->prepare("
        SELECT id, xero_invoice_number, match_status, matched_invoice_id, is_reconciled
        FROM xero_imported_invoices
        WHERE session_id = :session_id
    ");
    $stmt->execute(['session_id' => $sessionId]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($invoices as $invoice) {
        echo "  Invoice {$invoice['xero_invoice_number']}: ";
        echo "match_status={$invoice['match_status']}, ";
        echo "matched_id={$invoice['matched_invoice_id']}, ";
        echo "reconciled={$invoice['is_reconciled']}\n";
    }
    echo "\n";
}

