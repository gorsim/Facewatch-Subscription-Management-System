<?php
/**
 * Smart Match Results - Display matched invoices grouped by confidence
 * This file is included by smart_match.php when viewing a session
 * Variables available: $session, $matches, $matchingService
 */

if (!$session || !$matches) {
    echo '<div class="alert alert-danger">Session not found</div>';
    return;
}
?>

<div class="card" style="margin-bottom: 20px; background: #f8f9fa; border-left: 4px solid #28a745;">
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
        <div>
            <h2 style="margin: 0;">📊 Matching Results: <?= htmlspecialchars($session['session_name']) ?></h2>
            <p style="color: #666; margin: 5px 0 0 0;">
                Imported: <?= date('d/m/Y H:i', strtotime($session['import_date'])) ?>
                by <?= htmlspecialchars($session['imported_by']) ?>
            </p>
        </div>
        <div style="text-align: right;">
            <?php
            $statusBadges = [
                'pending' => '<span class="badge badge-secondary">Pending</span>',
                'reviewing' => '<span class="badge badge-warning">Reviewing</span>',
                'completed' => '<span class="badge badge-success">Completed</span>',
                'cancelled' => '<span class="badge badge-danger">Cancelled</span>'
            ];
            echo $statusBadges[$session['status']] ?? $session['status'];
            ?>
            <div style="margin-top: 5px; font-size: 0.9em; color: #666;">
                <?= $session['matched_count'] ?> / <?= $session['total_invoices'] ?> matched
                • <?= $session['reconciled_count'] ?> reconciled
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
        <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; color: #28a745; font-weight: bold;">
                <?= count($matches['perfect']) + count($matches['auto']) ?>
            </div>
            <div style="color: #666; font-size: 0.9em;">Perfect Matches</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; color: #ffc107; font-weight: bold;">
                <?= count($matches['suggested']) ?>
            </div>
            <div style="color: #666; font-size: 0.9em;">Suggested Matches</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; color: #ff9800; font-weight: bold;">
                <?= count($matches['possible']) ?>
            </div>
            <div style="color: #666; font-size: 0.9em;">Possible Matches</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; color: #dc3545; font-weight: bold;">
                <?= count($matches['unmatched']) ?>
            </div>
            <div style="color: #666; font-size: 0.9em;">Unmatched</div>
        </div>
    </div>
</div>

<?php
// Helper function to display match breakdown
function displayMatchBreakdown($breakdown) {
    $html = '<div style="font-size: 0.85em; color: #666; margin-top: 5px;">';
    
    if (isset($breakdown['entity'])) {
        $color = $breakdown['entity']['match'] === 'exact' ? '#28a745' : '#ffc107';
        $html .= '<div>📋 Entity: <span style="color: ' . $color . '; font-weight: bold;">' 
               . ucfirst($breakdown['entity']['match']) . ' (' . $breakdown['entity']['score'] . ' pts)</span></div>';
    }
    
    if (isset($breakdown['date'])) {
        $color = $breakdown['date']['match'] === 'exact' ? '#28a745' : '#ffc107';
        $html .= '<div>📅 Date: <span style="color: ' . $color . '; font-weight: bold;">' 
               . ucfirst($breakdown['date']['match']) . ' (' . $breakdown['date']['score'] . ' pts)</span></div>';
    }
    
    if (isset($breakdown['amount'])) {
        $color = $breakdown['amount']['match'] === 'exact' ? '#28a745' : '#ffc107';
        $html .= '<div>💰 Amount: <span style="color: ' . $color . '; font-weight: bold;">' 
               . ucfirst($breakdown['amount']['match']) . ' (' . $breakdown['amount']['score'] . ' pts)</span></div>';
    }
    
    $html .= '</div>';
    return $html;
}

// Helper function to display invoice comparison
function displayInvoiceComparison($systemInvoice, $xeroInvoice, $score, $breakdown) {
    ?>
    <div style="display: grid; grid-template-columns: 1fr 80px 1fr; gap: 15px; align-items: center; 
                background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
        <!-- System Invoice -->
        <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #2196F3;">
            <div style="font-weight: bold; color: #2196F3; margin-bottom: 8px;">📄 System Invoice</div>
            <div><strong>Invoice #:</strong> <?= htmlspecialchars($systemInvoice['invoice_number'] ?? 'N/A') ?></div>
            <div><strong>Xero Company:</strong> <?= htmlspecialchars($systemInvoice['xero_company_name'] ?? $systemInvoice['legal_entity_name'] ?? 'N/A') ?></div>
            <?php if (!empty($systemInvoice['xero_company_name']) && $systemInvoice['xero_company_name'] !== $systemInvoice['legal_entity_name']): ?>
                <div style="font-size: 0.85em; color: #666;"><strong>Legal Entity:</strong> <?= htmlspecialchars($systemInvoice['legal_entity_name'] ?? 'N/A') ?></div>
            <?php endif; ?>
            <div><strong>Date:</strong> <?= !empty($systemInvoice['invoice_date']) ? date('d/m/Y', strtotime($systemInvoice['invoice_date'])) : 'N/A' ?></div>
            <div><strong>Amount:</strong> £<?= $systemInvoice['invoice_amount'] !== null ? number_format($systemInvoice['invoice_amount'], 2) : '0.00' ?></div>
            <div style="margin-top: 8px;">
                <a href="?page=invoices&action=view&id=<?= htmlspecialchars($systemInvoice['id']) ?>"
                   class="btn btn-sm btn-primary" target="_blank">View Invoice</a>
            </div>
        </div>

        <!-- Match Score -->
        <div style="text-align: center;">
            <div style="font-size: 2em; font-weight: bold;
                        color: <?= $score >= 95 ? '#28a745' : ($score >= 70 ? '#ffc107' : '#ff9800') ?>;">
                <?= $score !== null ? number_format($score, 0) : '0' ?>%
            </div>
            <div style="font-size: 0.8em; color: #666;">Match</div>
            <?= displayMatchBreakdown($breakdown) ?>
        </div>
        
        <!-- Xero Invoice -->
        <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #00b8d4;">
            <div style="font-weight: bold; color: #00b8d4; margin-bottom: 8px;">📊 Xero Invoice</div>
            <div><strong>Invoice #:</strong> <?= htmlspecialchars($xeroInvoice['xero_invoice_number']) ?></div>
            <div><strong>Contact:</strong> <?= htmlspecialchars($xeroInvoice['contact_name']) ?></div>
            <div><strong>Date:</strong> <?= date('d/m/Y', strtotime($xeroInvoice['invoice_date'])) ?></div>
            <div><strong>Amount:</strong> £<?= number_format($xeroInvoice['amount'], 2) ?></div>
            <div style="margin-top: 8px; color: #666; font-size: 0.85em;">
                Xero ID: <?= htmlspecialchars($xeroInvoice['xero_invoice_id']) ?>
            </div>
        </div>
    </div>
    <?php
}
?>

<!-- Perfect Matches (100%) -->
<?php if (!empty($matches['perfect']) || !empty($matches['auto'])): ?>
<div class="card" style="border-left: 4px solid #28a745; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0; color: #28a745;">✅ Perfect Matches (<?= count($matches['perfect']) + count($matches['auto']) ?>)</h2>
        <?php if ($session['status'] !== 'completed'): ?>
            <?php
            // Check if perfect matches have been accepted
            $perfectMatchesAccepted = ($session['matched_count'] > 0);
            ?>
            <div style="display: flex; gap: 10px;">
                <form method="POST" action="?page=invoices&action=match_action" style="margin: 0;">
                    <input type="hidden" name="action_type" value="auto_accept_perfect">
                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                    <button type="submit"
                            class="btn <?= $perfectMatchesAccepted ? 'btn-secondary' : 'btn-success' ?>"
                            style="font-size: 1.1em; <?= $perfectMatchesAccepted ? 'opacity: 0.6; cursor: not-allowed;' : '' ?>"
                            <?= $perfectMatchesAccepted ? 'disabled' : '' ?>
                            onclick="return <?= $perfectMatchesAccepted ? 'false' : 'confirm(\'Auto-accept all ' . (count($matches['perfect']) + count($matches['auto'])) . ' perfect matches?\')' ?>">
                        <?= $perfectMatchesAccepted ? '✓ Perfect Matches Accepted' : '🚀 Auto-Accept All Perfect Matches' ?>
                    </button>
                </form>

                <?php if ($perfectMatchesAccepted): ?>
                <form method="POST" action="?page=invoices&action=match_action" style="margin: 0;">
                    <input type="hidden" name="action_type" value="reject_all_perfect">
                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                    <button type="submit"
                            class="btn btn-warning"
                            style="font-size: 1.1em;"
                            onclick="return confirm('Reject all <?= count($matches['perfect']) + count($matches['auto']) ?> perfect matches? This will reset them to unmatched.')">
                        ❌ Reject All Perfect Matches
                    </button>
                </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <p style="color: #666; margin-top: 10px;">
        These invoices match perfectly on entity name, date, and amount. <?= $perfectMatchesAccepted ? 'Already accepted!' : 'They can be auto-accepted.' ?>
    </p>
    
    <?php
    $perfectMatches = array_merge($matches['perfect'], $matches['auto']);
    foreach ($perfectMatches as $match):
        displayInvoiceComparison(
            $match['system_invoice'],
            $match['xero_invoice'],
            $match['score'],
            $match['breakdown']
        );

        // Add individual accept/reject buttons for each perfect match
        if ($session['status'] !== 'completed'): ?>
        <div style="text-align: center; margin-top: -10px; margin-bottom: 20px;">
            <form method="POST" action="?page=invoices&action=match_action" style="display: inline-block; margin-right: 10px;">
                <input type="hidden" name="action_type" value="accept_match">
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <input type="hidden" name="xero_invoice_id" value="<?= $match['xero_invoice']['id'] ?>">
                <input type="hidden" name="system_invoice_id" value="<?= $match['system_invoice']['id'] ?>">
                <input type="hidden" name="score" value="<?= $match['score'] ?>">
                <input type="hidden" name="breakdown" value="<?= htmlspecialchars(json_encode($match['breakdown'])) ?>">
                <button type="submit" class="btn btn-success">
                    ✅ Accept Match
                </button>
            </form>
            <form method="POST" action="?page=invoices&action=match_action" style="display: inline-block;">
                <input type="hidden" name="action_type" value="reject_match">
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <input type="hidden" name="xero_invoice_id" value="<?= $match['xero_invoice']['id'] ?>">
                <input type="hidden" name="system_invoice_id" value="<?= $match['system_invoice']['id'] ?>">
                <button type="submit" class="btn btn-danger">
                    ❌ No Match
                </button>
            </form>
        </div>
        <?php endif;
    endforeach;
    ?>
</div>
<?php endif; ?>

<!-- Suggested Matches (70-99%) -->
<?php if (!empty($matches['suggested'])): ?>
<div class="card" style="border-left: 4px solid #ffc107; margin-bottom: 20px;">
    <h2 style="margin: 0; color: #f57c00;">💡 Suggested Matches (<?= count($matches['suggested']) ?>)</h2>
    <p style="color: #666; margin-top: 10px;">
        These invoices are likely matches but require your review. Accept or reject each match.
    </p>

    <?php foreach ($matches['suggested'] as $match): ?>
        <?php displayInvoiceComparison(
            $match['system_invoice'],
            $match['xero_invoice'],
            $match['score'],
            $match['breakdown']
        ); ?>
        <div style="text-align: center; margin-top: -10px; margin-bottom: 20px;">
            <form method="POST" action="?page=invoices&action=match_action" style="display: inline-block; margin-right: 10px;">
                <input type="hidden" name="action_type" value="accept_match">
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <input type="hidden" name="xero_invoice_id" value="<?= $match['xero_invoice']['id'] ?>">
                <input type="hidden" name="system_invoice_id" value="<?= $match['system_invoice']['id'] ?>">
                <input type="hidden" name="score" value="<?= $match['score'] ?>">
                <input type="hidden" name="breakdown" value="<?= htmlspecialchars(json_encode($match['breakdown'])) ?>">
                <button type="submit" class="btn btn-success">
                    ✅ Accept Match
                </button>
            </form>
            <form method="POST" action="?page=invoices&action=match_action" style="display: inline-block;">
                <input type="hidden" name="action_type" value="reject_match">
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <input type="hidden" name="xero_invoice_id" value="<?= $match['xero_invoice']['id'] ?>">
                <input type="hidden" name="system_invoice_id" value="<?= $match['system_invoice']['id'] ?>">
                <button type="submit" class="btn btn-danger">
                    ❌ Reject Match
                </button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Possible Matches (<70%) -->
<?php if (!empty($matches['possible'])): ?>
<div class="card" style="border-left: 4px solid #ff9800; margin-bottom: 20px;">
    <h2 style="margin: 0; color: #ff9800;">🤔 Possible Matches (<?= count($matches['possible']) ?>)</h2>
    <p style="color: #666; margin-top: 10px;">
        These are lower confidence matches. Review carefully before accepting.
    </p>

    <?php foreach ($matches['possible'] as $match): ?>
        <?php displayInvoiceComparison(
            $match['system_invoice'],
            $match['xero_invoice'],
            $match['score'],
            $match['breakdown']
        ); ?>
        <div style="text-align: center; margin-top: -10px; margin-bottom: 20px;">
            <form method="POST" action="?page=invoices&action=match_action" style="display: inline-block; margin-right: 10px;">
                <input type="hidden" name="action_type" value="accept_match">
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <input type="hidden" name="xero_invoice_id" value="<?= $match['xero_invoice']['id'] ?>">
                <input type="hidden" name="system_invoice_id" value="<?= $match['system_invoice']['id'] ?>">
                <input type="hidden" name="score" value="<?= $match['score'] ?>">
                <input type="hidden" name="breakdown" value="<?= htmlspecialchars(json_encode($match['breakdown'])) ?>">
                <button type="submit" class="btn btn-success"
                        onclick="return confirm('This is a low confidence match (<?= $match['score'] ?>%). Are you sure?')">
                    ✅ Accept Match
                </button>
            </form>
            <form method="POST" action="?page=invoices&action=match_action" style="display: inline-block; margin-right: 10px;">
                <input type="hidden" name="action_type" value="reject_match">
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <input type="hidden" name="xero_invoice_id" value="<?= $match['xero_invoice']['id'] ?>">
                <input type="hidden" name="system_invoice_id" value="<?= $match['system_invoice']['id'] ?>">
                <button type="submit" class="btn btn-danger">
                    ❌ Reject Match
                </button>
            </form>
            <a href="?page=invoices&action=manual_match&xero_invoice_id=<?= $match['xero_invoice']['id'] ?>&session_id=<?= $session['id'] ?>"
               class="btn btn-secondary">
                🔍 Find Different Match
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Unmatched Invoices -->
<?php if (!empty($matches['unmatched'])): ?>
<div class="card" style="border-left: 4px solid #dc3545; margin-bottom: 20px;">
    <h2 style="margin: 0; color: #dc3545;">❌ Unmatched Xero Invoices (<?= count($matches['unmatched']) ?>)</h2>
    <p style="color: #666; margin-top: 10px;">
        These Xero invoices have no matching system invoice. Use "Find Match" to manually match them, or investigate why they're missing.
    </p>

    <table>
        <thead>
            <tr>
                <th>Xero Invoice #</th>
                <th>Contact Name</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($matches['unmatched'] as $item): ?>
                <?php $xeroInvoice = $item['xero_invoice']; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($xeroInvoice['xero_invoice_number']) ?></strong></td>
                    <td><?= htmlspecialchars($xeroInvoice['contact_name']) ?></td>
                    <td><?= date('d/m/Y', strtotime($xeroInvoice['invoice_date'])) ?></td>
                    <td>£<?= number_format($xeroInvoice['amount'], 2) ?></td>
                    <td><span class="badge badge-warning"><?= htmlspecialchars($xeroInvoice['status']) ?></span></td>
                    <td>
                        <a href="?page=invoices&action=manual_match&xero_invoice_id=<?= $xeroInvoice['id'] ?>&session_id=<?= $session['id'] ?>"
                           class="btn btn-sm btn-primary">
                            🔍 Find Match
                        </a>
                        <a href="?page=invoices&action=create"
                           class="btn btn-sm btn-secondary"
                           title="Create a new invoice in the system">
                            ➕ Create Invoice
                        </a>
                        <form method="POST" action="?page=invoices&action=delete_xero_invoice" style="display: inline;">
                            <input type="hidden" name="xero_invoice_id" value="<?= $xeroInvoice['id'] ?>">
                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="margin-left: 5px;"
                                    onclick="return confirm('Delete Xero invoice <?= htmlspecialchars($xeroInvoice['xero_invoice_number'] ?? 'this invoice', ENT_QUOTES) ?>?\n\nThis cannot be undone.');">
                                🗑️ Delete
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Action Buttons -->
<div style="text-align: center; margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
    <a href="?page=invoices&action=smart_match" class="btn btn-secondary" style="margin-right: 10px;">
        ⬅️ Back to Import
    </a>

    <?php
    // Count accepted matches that haven't been reconciled
    $acceptedCount = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM xero_imported_invoices
        WHERE session_id = :session_id
        AND match_status IN ('auto_matched', 'manual_matched')
        AND is_reconciled = FALSE
    ", ['session_id' => $session['id']])['count'] ?? 0;
    ?>

    <?php if ($session['status'] === 'completed'): ?>
        <!-- Session is completed - show re-open button -->
        <form method="POST" action="?page=invoices&action=match_action" style="display: inline-block;">
            <input type="hidden" name="action_type" value="reopen_session">
            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
            <button type="submit" class="btn btn-warning" style="font-size: 1.1em; padding: 12px 24px;">
                🔄 Re-Open Reconciliation
            </button>
        </form>
    <?php elseif ($acceptedCount > 0): ?>
        <!-- Session is active and has matches to reconcile -->
        <form method="POST" action="?page=invoices&action=match_action" style="display: inline-block;">
            <input type="hidden" name="action_type" value="bulk_reconcile">
            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
            <button type="submit" class="btn btn-success"
                    style="font-size: 1.1em; padding: 12px 24px; background-color: #28a745; border-color: #28a745; animation: pulse-green 2s infinite;"
                    onclick="return confirm('Reconcile <?= $acceptedCount ?> matched invoices to Xero?')">
                🎯 Reconcile All Accepted Matches (<?= $acceptedCount ?>)
            </button>
        </form>
        <style>
            @keyframes pulse-green {
                0%, 100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
                50% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            }
        </style>
    <?php elseif ($session['reconciled_count'] > 0): ?>
        <!-- All matches are reconciled - show complete button -->
        <form method="POST" action="?page=invoices&action=match_action" style="display: inline-block;">
            <input type="hidden" name="action_type" value="complete_session">
            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
            <button type="submit" class="btn btn-success" style="font-size: 1.1em; padding: 12px 24px;">
                ✅ Mark Session as Complete
            </button>
        </form>
    <?php else: ?>
        <!-- Session is active but no matches accepted yet - show disabled reconcile button -->
        <button type="button" class="btn btn-secondary"
                style="font-size: 1.1em; padding: 12px 24px; opacity: 0.5; cursor: not-allowed;"
                disabled
                title="Accept matches first before reconciling">
            🎯 Reconcile All Accepted Matches (0)
        </button>
    <?php endif; ?>
</div>

