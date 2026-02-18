<?php
/**
 * Match Action Handler - Accept/Reject matches and bulk reconcile
 */

use App\Database;
use App\Services\InvoiceMatchingService;
use App\Services\InvoiceStatusService;

$db = Database::getInstance();
$matchingService = new InvoiceMatchingService();
$statusService = new InvoiceStatusService();

// Get action type
$actionType = $_POST['action_type'] ?? $_GET['action_type'] ?? null;

error_log("=== MATCH_ACTION.PHP CALLED ===");
error_log("Action type: " . ($actionType ?? 'NULL'));
error_log("POST data: " . print_r($_POST, true));

if (!$actionType) {
    error_log("ERROR: No action type specified!");
    $_SESSION['error'] = 'No action specified';
    header('Location: ?page=invoices&action=smart_match');
    exit;
}

try {
    switch ($actionType) {
        case 'accept_match':
            // Accept a single match
            $xeroInvoiceId = $_POST['xero_invoice_id'] ?? null;
            $systemInvoiceId = $_POST['system_invoice_id'] ?? null;
            $score = $_POST['score'] ?? 0;
            $breakdown = json_decode($_POST['breakdown'] ?? '{}', true);
            $sessionId = $_POST['session_id'] ?? null;
            
            if (!$xeroInvoiceId || !$systemInvoiceId) {
                throw new Exception('Missing invoice IDs');
            }
            
            $matchingService->acceptMatch(
                $xeroInvoiceId,
                $systemInvoiceId,
                $score,
                $breakdown,
                $_SESSION['user_email'] ?? 'system'
            );

            // Update session matched count (two-step to avoid parameter binding issues)
            $matchedCount = $db->fetchOne("
                SELECT COUNT(*) as count
                FROM xero_imported_invoices
                WHERE session_id = :session_id
                AND match_status IN ('auto_matched', 'manual_matched')
            ", ['session_id' => $sessionId])['count'];

            $db->query("
                UPDATE xero_import_sessions
                SET matched_count = :count
                WHERE id = :session_id
            ", ['count' => $matchedCount, 'session_id' => $sessionId]);

            $_SESSION['success'] = 'Match accepted successfully!';
            header("Location: ?page=invoices&action=smart_match&session=$sessionId");
            exit;
            
        case 'reject_match':
            // Reject a single match
            $xeroInvoiceId = $_POST['xero_invoice_id'] ?? null;
            $systemInvoiceId = $_POST['system_invoice_id'] ?? null;
            $sessionId = $_POST['session_id'] ?? null;

            if (!$xeroInvoiceId) {
                throw new Exception('Missing Xero invoice ID');
            }

            $matchingService->rejectMatch(
                $xeroInvoiceId,
                $systemInvoiceId,
                $_SESSION['user_email'] ?? 'system'
            );

            // Update session matched count (two-step to avoid parameter binding issues)
            $matchedCount = $db->fetchOne("
                SELECT COUNT(*) as count
                FROM xero_imported_invoices
                WHERE session_id = :session_id
                AND match_status IN ('auto_matched', 'manual_matched')
            ", ['session_id' => $sessionId])['count'];

            $db->query("
                UPDATE xero_import_sessions
                SET matched_count = :count
                WHERE id = :session_id
            ", ['count' => $matchedCount, 'session_id' => $sessionId]);

            $_SESSION['success'] = 'Match rejected';
            header("Location: ?page=invoices&action=smart_match&session=$sessionId");
            exit;
            
        case 'auto_accept_perfect':
            // Auto-accept all perfect matches
            $sessionId = $_POST['session_id'] ?? null;

            if (!$sessionId) {
                throw new Exception('Missing session ID');
            }

            // Check if there are existing matches in the database (for re-opened sessions)
            $existingMatches = $db->fetchOne("
                SELECT COUNT(*) as count
                FROM xero_imported_invoices
                WHERE session_id = :session_id
                AND matched_invoice_id IS NOT NULL
            ", ['session_id' => $sessionId]);

            $hasExistingMatches = ($existingMatches['count'] > 0);

            // Get all perfect matches - use getSessionMatches for re-opened sessions
            if ($hasExistingMatches) {
                $matches = $matchingService->getSessionMatches($sessionId);
            } else {
                $matches = $matchingService->findMatches($sessionId);
            }

            $perfectMatches = array_merge($matches['perfect'], $matches['auto']);

            $acceptedCount = 0;
            foreach ($perfectMatches as $match) {
                $matchingService->acceptMatch(
                    $match['xero_invoice']['id'],
                    $match['system_invoice']['id'],
                    $match['score'],
                    $match['breakdown'],
                    $_SESSION['user_email'] ?? 'system'
                );
                $acceptedCount++;
            }

            // Update session matched count
            error_log("About to update session counts for session $sessionId");

            // First check what the count should be
            $countCheck = $db->fetchOne("
                SELECT COUNT(*) as count
                FROM xero_imported_invoices
                WHERE session_id = :session_id
                AND match_status IN ('auto_matched', 'manual_matched')
            ", ['session_id' => $sessionId]);
            error_log("Count check: " . $countCheck['count'] . " matched invoices found");

            // Use a simpler two-step approach instead of subquery
            $matchedCount = $countCheck['count'];
            error_log("Updating session $sessionId to matched_count = $matchedCount");

            $stmt = $db->query("
                UPDATE xero_import_sessions
                SET matched_count = :matched_count
                WHERE id = :session_id
            ", [
                'matched_count' => $matchedCount,
                'session_id' => $sessionId
            ]);

            if (!$stmt) {
                error_log("ERROR: Failed to update session counts!");
            }

            // Verify the update worked
            $sessionCheck = $db->fetchOne("
                SELECT matched_count FROM xero_import_sessions WHERE id = :session_id
            ", ['session_id' => $sessionId]);
            error_log("After update: matched_count = " . $sessionCheck['matched_count']);

            $_SESSION['success'] = "Auto-accepted $acceptedCount perfect matches!";
            header("Location: ?page=invoices&action=smart_match&session=$sessionId");
            exit;

        case 'reject_all_perfect':
            // Reject all perfect matches (reset them to unmatched)
            $sessionId = $_POST['session_id'] ?? null;

            if (!$sessionId) {
                throw new Exception('Missing session ID');
            }

            error_log("=== REJECT ALL PERFECT MATCHES ===");
            error_log("Session ID: $sessionId");

            // Get ALL accepted matches (don't filter by score - reject everything that's been accepted)
            $perfectMatches = $db->fetchAll("
                SELECT id, match_status, match_score, matched_invoice_id, is_reconciled
                FROM xero_imported_invoices
                WHERE session_id = :session_id
                AND match_status IN ('auto_matched', 'manual_matched')
            ", ['session_id' => $sessionId]);

            error_log("Found " . count($perfectMatches) . " accepted matches to reject");

            // Log details of each match
            foreach ($perfectMatches as $match) {
                error_log("  - Match ID {$match['id']}: status={$match['match_status']}, score={$match['match_score']}, matched_invoice_id={$match['matched_invoice_id']}, is_reconciled={$match['is_reconciled']}");
            }

            $rejectedCount = 0;
            foreach ($perfectMatches as $match) {
                // Reset the match AND the reconciliation flag
                // Note: Set match_status to 'unmatched' (not NULL) so findMatches() can find it
                $db->query("
                    UPDATE xero_imported_invoices
                    SET match_status = 'unmatched',
                        matched_invoice_id = NULL,
                        match_confidence = NULL,
                        match_reason = NULL,
                        is_reconciled = 0
                    WHERE id = :id
                ", ['id' => $match['id']]);

                error_log("  - Reset match ID {$match['id']}");
                $rejectedCount++;
            }

            // Update session counts to 0
            $db->query("
                UPDATE xero_import_sessions
                SET matched_count = 0,
                    reconciled_count = 0
                WHERE id = :session_id
            ", ['session_id' => $sessionId]);

            error_log("Rejected $rejectedCount matches, reset matched_count and reconciled_count to 0");

            $_SESSION['success'] = "Rejected $rejectedCount matches. They are now unmatched.";
            header("Location: ?page=invoices&action=smart_match&session=$sessionId");
            exit;

        case 'bulk_reconcile':
            error_log("=== BULK RECONCILE STARTED ===");
            error_log("POST data: " . print_r($_POST, true));

            // Reconcile all accepted matches
            $sessionId = $_POST['session_id'] ?? null;
            error_log("Session ID: $sessionId");

            if (!$sessionId) {
                error_log("ERROR: Missing session ID!");
                throw new Exception('Missing session ID');
            }
            
            // Get all accepted matches that haven't been reconciled yet
            error_log("Fetching accepted matches for session $sessionId...");
            $acceptedMatches = $db->fetchAll("
                SELECT * FROM xero_imported_invoices
                WHERE session_id = :session_id
                AND match_status IN ('auto_matched', 'manual_matched')
                AND is_reconciled = FALSE
                AND matched_invoice_id IS NOT NULL
            ", ['session_id' => $sessionId]);

            error_log("Found " . count($acceptedMatches) . " matches to reconcile");

            $reconciledCount = 0;
            $errors = [];

            foreach ($acceptedMatches as $match) {
                error_log("Processing match: Xero #{$match['xero_invoice_number']} -> System #{$match['matched_invoice_id']}");
                try {
                    // Reconcile the system invoice to Xero
                    error_log("Calling reconcileToXero...");
                    $statusService->reconcileToXero(
                        $match['matched_invoice_id'],
                        $match['xero_invoice_id'],
                        $match['xero_invoice_number'],
                        $_SESSION['user_email'] ?? 'system'
                    );
                    error_log("reconcileToXero completed successfully");

                    // Mark as reconciled in xero_imported_invoices
                    error_log("Marking Xero invoice as reconciled...");
                    $db->update('xero_imported_invoices', [
                        'is_reconciled' => true,
                        'reconciled_at' => date('Y-m-d H:i:s')
                    ], 'id = :id', ['id' => $match['id']]);
                    error_log("Marked as reconciled");

                    $reconciledCount++;

                } catch (Exception $e) {
                    error_log("ERROR reconciling: " . $e->getMessage());
                    $errors[] = "Invoice #{$match['xero_invoice_number']}: " . $e->getMessage();
                }
            }

            error_log("Reconciliation loop complete. Reconciled: $reconciledCount, Errors: " . count($errors));
            
            // Update session reconciled count (but keep status as 'reviewing')
            error_log("Updating session reconciled count...");

            // First, get the count
            $reconciledCountCheck = $db->fetchOne("
                SELECT COUNT(*) as count
                FROM xero_imported_invoices
                WHERE session_id = :session_id
                AND is_reconciled = TRUE
            ", ['session_id' => $sessionId]);

            error_log("Reconciled count from query: " . $reconciledCountCheck['count']);

            // Then update with the direct value
            $db->query("
                UPDATE xero_import_sessions
                SET reconciled_count = :reconciled_count
                WHERE id = :session_id
            ", [
                'reconciled_count' => $reconciledCountCheck['count'],
                'session_id' => $sessionId
            ]);

            // Verify the update worked
            $sessionCheck = $db->fetchOne("
                SELECT reconciled_count FROM xero_import_sessions WHERE id = :session_id
            ", ['session_id' => $sessionId]);
            error_log("After update: reconciled_count in DB = " . $sessionCheck['reconciled_count']);

            if ($reconciledCount > 0) {
                error_log("Setting success message: $reconciledCount invoices reconciled");
                $_SESSION['success'] = "Successfully reconciled $reconciledCount invoices to Xero!";
            }

            if (!empty($errors)) {
                error_log("Setting error message: " . implode('; ', $errors));
                $_SESSION['error'] = "Some invoices failed: " . implode('; ', $errors);
            }

            error_log("Redirecting back to smart_match page...");
            header("Location: ?page=invoices&action=smart_match&session=$sessionId");
            error_log("After header redirect (this should not appear)");
            
            header("Location: ?page=invoices&action=smart_match&session=$sessionId");
            exit;

        case 'complete_session':
            // Mark session as completed
            $sessionId = $_POST['session_id'] ?? null;

            if (!$sessionId) {
                throw new Exception('Missing session ID');
            }

            // Update session status to completed
            $db->update('xero_import_sessions', [
                'status' => 'completed'
            ], 'id = :id', ['id' => $sessionId]);

            $_SESSION['success'] = 'Session marked as completed!';
            header("Location: ?page=invoices&action=smart_match&session=$sessionId");
            exit;

        case 'reopen_session':
            // Re-open a completed session for further work
            $sessionId = $_POST['session_id'] ?? null;

            if (!$sessionId) {
                throw new Exception('Missing session ID');
            }

            // Un-reconcile all invoices in this session so they can be worked on again
            $db->query("
                UPDATE xero_imported_invoices
                SET is_reconciled = FALSE,
                    reconciled_at = NULL
                WHERE session_id = :session_id
                AND is_reconciled = TRUE
            ", ['session_id' => $sessionId]);

            // Also update the system invoices to remove reconciliation status
            // Reset invoice_status back to 'issued' so they can be reconciled again
            error_log("Re-opening session $sessionId: Resetting system invoices to 'issued' status");
            $db->query("
                UPDATE invoices
                SET invoice_status = 'issued',
                    reconciliation_status = 'pending',
                    xero_invoice_id = NULL,
                    xero_invoice_number = NULL
                WHERE id IN (
                    SELECT matched_invoice_id
                    FROM xero_imported_invoices
                    WHERE session_id = :session_id
                    AND matched_invoice_id IS NOT NULL
                )
            ", ['session_id' => $sessionId]);
            error_log("System invoices reset to 'issued' status");

            // Update session status back to reviewing and reset reconciled count
            $db->update('xero_import_sessions', [
                'status' => 'reviewing',
                'reconciled_count' => 0
            ], 'id = :id', ['id' => $sessionId]);

            $_SESSION['success'] = 'Session re-opened! All reconciliations have been reversed so you can make changes.';
            header("Location: ?page=invoices&action=smart_match&session=$sessionId");
            exit;

        default:
            throw new Exception('Invalid action type');
    }
    
} catch (Exception $e) {
    error_log("=== EXCEPTION CAUGHT IN MATCH_ACTION.PHP ===");
    error_log("Error message: " . $e->getMessage());
    error_log("Exception trace: " . $e->getTraceAsString());

    $_SESSION['error'] = 'Action failed: ' . $e->getMessage();
    $sessionId = $_POST['session_id'] ?? $_GET['session_id'] ?? null;
    if ($sessionId) {
        header("Location: ?page=invoices&action=smart_match&session=$sessionId");
    } else {
        header("Location: ?page=invoices&action=smart_match");
    }
    exit;
}

