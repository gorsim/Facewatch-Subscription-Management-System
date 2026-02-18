<?php
/**
 * Manual Match - Manually match an unmatched Xero invoice to a system invoice
 */

use App\Database;
use App\Services\InvoiceMatchingService;

$db = Database::getInstance();
$matchingService = new InvoiceMatchingService();

// Get Xero invoice ID and session ID
$xeroInvoiceId = $_GET['xero_invoice_id'] ?? null;
$sessionId = $_GET['session_id'] ?? null;

// Handle manual match submission FIRST - before any output
error_log("=== MANUAL_MATCH.PHP LOADED ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['system_invoice_id'])) {
    error_log("=== MANUAL MATCH FORM SUBMITTED ===");

    if (!$xeroInvoiceId || !$sessionId) {
        $_SESSION['error'] = 'Missing invoice or session ID';
        header('Location: ?page=invoices&action=smart_match');
        exit;
    }

    // Get the Xero invoice
    $xeroInvoice = $db->fetchOne("
        SELECT * FROM xero_imported_invoices
        WHERE id = :id
    ", ['id' => $xeroInvoiceId]);

    if (!$xeroInvoice) {
        $_SESSION['error'] = 'Xero invoice not found';
        header('Location: ?page=invoices&action=smart_match');
        exit;
    }

    try {
        $systemInvoiceId = $_POST['system_invoice_id'];
        error_log("System invoice ID selected: " . $systemInvoiceId);
        
        if (empty($systemInvoiceId)) {
            throw new Exception('Please select a system invoice');
        }
        
        // Get the system invoice to calculate score
        // Include xero_company_name for accurate matching
        $systemInvoice = $db->fetchOne("
            SELECT i.*, le.legal_entity_name, le.xero_company_name
            FROM invoices i
            JOIN legal_entities le ON i.legal_entity_id = le.id
            WHERE i.id = :id
        ", ['id' => $systemInvoiceId]);
        
        if (!$systemInvoice) {
            throw new Exception('System invoice not found');
        }
        
        // Calculate match score
        $matchResult = $matchingService->calculateMatchScore($systemInvoice, $xeroInvoice);
        
        // Accept the manual match
        error_log("Calling acceptMatch...");
        $matchingService->acceptMatch(
            $xeroInvoiceId,
            $systemInvoiceId,
            $matchResult['total_score'],
            $matchResult['breakdown'],
            $_SESSION['user_email'] ?? 'system'
        );
        error_log("acceptMatch completed");

        // Update to mark as manual match
        error_log("Updating match_status to manual_matched...");
        $db->update('xero_imported_invoices', [
            'match_status' => 'manual_matched'
        ], 'id = :id', ['id' => $xeroInvoiceId]);
        error_log("Updated match_status");

        // Update session matched count
        error_log("Updating session matched count...");
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
        error_log("Session matched count updated to: $matchedCount");

        $_SESSION['success'] = 'Manual match created successfully! Score: ' . $matchResult['total_score'] . '%';
        error_log("Redirecting to: ?page=invoices&action=smart_match&session=$sessionId");
        header("Location: ?page=invoices&action=smart_match&session=$sessionId");
        exit;

    } catch (Exception $e) {
        error_log("Manual match error: " . $e->getMessage());
        $_SESSION['error'] = 'Manual match failed: ' . $e->getMessage();
        header("Location: ?page=invoices&action=manual_match&xero_invoice_id=$xeroInvoiceId&session_id=$sessionId");
        exit;
    }
}

// Now handle GET requests - validation and page display
$pageTitle = 'Manual Match Invoice';
$page = 'invoices';

if (!$xeroInvoiceId || !$sessionId) {
    $_SESSION['error'] = 'Missing invoice or session ID';
    header('Location: ?page=invoices&action=smart_match');
    exit;
}

// Get the Xero invoice
$xeroInvoice = $db->fetchOne("
    SELECT * FROM xero_imported_invoices
    WHERE id = :id
", ['id' => $xeroInvoiceId]);

if (!$xeroInvoice) {
    $_SESSION['error'] = 'Xero invoice not found';
    header('Location: ?page=invoices&action=smart_match');
    exit;
}

// Get session
$session = $db->fetchOne("SELECT * FROM xero_import_sessions WHERE id = :id", ['id' => $sessionId]);

// Get all available system invoices (issued status, not already matched in this session)
// Include xero_company_name for accurate matching
$availableInvoices = $db->fetchAll("
    SELECT i.*, le.legal_entity_name, le.xero_company_name
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE i.invoice_status = 'issued'
    AND i.id NOT IN (
        SELECT matched_invoice_id
        FROM xero_imported_invoices
        WHERE matched_invoice_id IS NOT NULL
        AND session_id = :session_id
    )
    ORDER BY i.invoice_date DESC
", ['session_id' => $sessionId]);

// Calculate match scores for all available invoices to help user choose
$scoredInvoices = [];
foreach ($availableInvoices as $invoice) {
    $matchResult = $matchingService->calculateMatchScore($invoice, $xeroInvoice);
    $scoredInvoices[] = [
        'invoice' => $invoice,
        'score' => $matchResult['total_score'],
        'breakdown' => $matchResult['breakdown']
    ];
}

// Sort by score descending
usort($scoredInvoices, function($a, $b) {
    return $b['score'] - $a['score'];
});

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>🔍 Manual Match Invoice</h1>
        <a href="?page=invoices&action=smart_match&session=<?= $sessionId ?>" class="btn">← Back to Results</a>
    </div>

    <!-- Xero Invoice Details -->
    <div class="card" style="margin-bottom: 20px; background: #e7f3ff; border-left: 4px solid #00b8d4;">
        <h2 style="margin-top: 0; color: #00b8d4;">📊 Xero Invoice to Match</h2>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div>
                <strong>Invoice Number:</strong> <?= htmlspecialchars($xeroInvoice['xero_invoice_number']) ?>
            </div>
            <div>
                <strong>Xero ID:</strong> <?= htmlspecialchars($xeroInvoice['xero_invoice_id']) ?>
            </div>
            <div>
                <strong>Contact Name:</strong> <?= htmlspecialchars($xeroInvoice['contact_name']) ?>
            </div>
            <div>
                <strong>Invoice Date:</strong> <?= date('d/m/Y', strtotime($xeroInvoice['invoice_date'])) ?>
            </div>
            <div>
                <strong>Amount:</strong> £<?= number_format($xeroInvoice['amount'], 2) ?>
            </div>
            <div>
                <strong>Status:</strong> <span class="badge badge-info"><?= htmlspecialchars($xeroInvoice['status']) ?></span>
            </div>
        </div>
    </div>

    <!-- Manual Match Form -->
    <div class="card">
        <h2>Select System Invoice to Match</h2>
        <p style="color: #666; margin-bottom: 20px;">
            Choose the system invoice that corresponds to the Xero invoice above. 
            Invoices are sorted by match score to help you find the best match.
        </p>

        <?php if (empty($scoredInvoices)): ?>
            <div class="alert alert-warning">
                <strong>No available invoices!</strong><br>
                All issued invoices are already matched, or there are no issued invoices in the system.
            </div>
            <a href="?page=invoices&action=smart_match&session=<?= $sessionId ?>" class="btn">← Back to Results</a>
        <?php else: ?>
            <form method="POST" action="?page=invoices&action=manual_match&xero_invoice_id=<?= $xeroInvoiceId ?>&session_id=<?= $sessionId ?>">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">Select</th>
                            <th>Invoice #</th>
                            <th>Legal Entity</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Match Score</th>
                            <th>Match Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scoredInvoices as $item): ?>
                            <?php 
                            $invoice = $item['invoice'];
                            $score = $item['score'];
                            $breakdown = $item['breakdown'];
                            
                            // Color code by score
                            $scoreColor = $score >= 70 ? '#28a745' : ($score >= 50 ? '#ffc107' : '#ff9800');
                            ?>
                            <tr style="cursor: pointer;" onclick="document.getElementById('invoice_<?= $invoice['id'] ?>').checked = true;">
                                <td>
                                    <input type="radio" 
                                           name="system_invoice_id" 
                                           id="invoice_<?= $invoice['id'] ?>" 
                                           value="<?= $invoice['id'] ?>" 
                                           required>
                                </td>
                                <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                                <td><?= htmlspecialchars($invoice['legal_entity_name']) ?></td>
                                <td><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></td>
                                <td>£<?= number_format($invoice['invoice_amount'], 2) ?></td>
                                <td>
                                    <span style="font-size: 1.2em; font-weight: bold; color: <?= $scoreColor ?>;">
                                        <?= $score ?>%
                                    </span>
                                </td>
                                <td style="font-size: 0.85em; color: #666;">
                                    <?php if (isset($breakdown['entity'])): ?>
                                        Entity: <?= ucfirst($breakdown['entity']['match']) ?> (<?= $breakdown['entity']['score'] ?>pts)<br>
                                    <?php endif; ?>
                                    <?php if (isset($breakdown['date'])): ?>
                                        Date: <?= ucfirst($breakdown['date']['match']) ?> (<?= $breakdown['date']['score'] ?>pts)<br>
                                    <?php endif; ?>
                                    <?php if (isset($breakdown['amount'])): ?>
                                        Amount: <?= ucfirst($breakdown['amount']['match']) ?> (<?= $breakdown['amount']['score'] ?>pts)
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 20px; text-align: center;">
                    <button type="submit" class="btn btn-success" style="font-size: 1.1em; padding: 12px 24px;">
                        ✅ Create Manual Match
                    </button>
                    <a href="?page=invoices&action=smart_match&session=<?= $sessionId ?>" class="btn" style="margin-left: 10px;">
                        Cancel
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

