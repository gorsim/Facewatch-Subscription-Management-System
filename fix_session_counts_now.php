<?php
require_once 'subscription-system/app/Database.php';
use App\Database;

$db = Database::getInstance();

echo "Fixing session counts...\n\n";

// Get all sessions
$sessions = $db->fetchAll('SELECT id, session_name FROM xero_import_sessions');

foreach ($sessions as $session) {
    $sessionId = $session['id'];
    
    // Count total invoices
    $totalCount = $db->fetchOne(
        'SELECT COUNT(*) as count FROM xero_imported_invoices WHERE session_id = :session_id',
        ['session_id' => $sessionId]
    )['count'];

    // Count matched invoices
    $matchedCount = $db->fetchOne(
        'SELECT COUNT(*) as count FROM xero_imported_invoices
         WHERE session_id = :session_id 
         AND match_status IN ("auto_matched", "manual_matched")',
        ['session_id' => $sessionId]
    )['count'];

    // Count reconciled invoices
    $reconciledCount = $db->fetchOne(
        'SELECT COUNT(*) as count FROM xero_imported_invoices
         WHERE session_id = :session_id 
         AND is_reconciled = TRUE',
        ['session_id' => $sessionId]
    )['count'];

    // Update session
    $db->update('xero_import_sessions',
        [
            'total_invoices' => $totalCount,
            'matched_count' => $matchedCount,
            'reconciled_count' => $reconciledCount
        ],
        'id = :id',
        ['id' => $sessionId]
    );
    
    echo "✅ Updated session '{$session['session_name']}': {$totalCount} total, {$matchedCount} matched, {$reconciledCount} reconciled\n";
}

echo "\n✅ All session counts updated!\n";

