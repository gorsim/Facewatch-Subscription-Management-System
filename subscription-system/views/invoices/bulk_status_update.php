<?php
/**
 * Bulk Invoice Status Update
 * Allows marking multiple invoices as "issued" for historic data cleanup
 */

use App\Database;
use App\Services\InvoiceStatusService;

$pageTitle = 'Bulk Status Update';
$page = 'invoices';

$db = Database::getInstance();
$statusService = new InvoiceStatusService();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'mark_all_issued') {
            // Get all non-forecast invoices that are not already issued or reconciled
            $invoices = $db->fetchAll("
                SELECT id, invoice_number, invoice_status
                FROM invoices
                WHERE (is_forecast = FALSE OR is_forecast IS NULL)
                AND invoice_status NOT IN ('issued', 'reconciled_to_xero')
                ORDER BY invoice_date
            ");

            $updated = 0;
            foreach ($invoices as $invoice) {
                $statusService->changeStatus(
                    $invoice['id'],
                    'issued',
                    $_SESSION['user_email'] ?? 'system',
                    'Bulk status update - historic data cleanup'
                );
                $updated++;
            }

            $_SESSION['success'] = "✅ Successfully marked {$updated} invoices as 'issued'";
            header('Location: ?page=invoices');
            exit;

        } elseif ($_POST['action'] === 'mark_selected_issued') {
            if (empty($_POST['invoice_ids'])) {
                $_SESSION['error'] = "No invoices selected";
            } else {
                $invoiceIds = $_POST['invoice_ids'];
                $updated = 0;

                foreach ($invoiceIds as $invoiceId) {
                    $statusService->changeStatus(
                        $invoiceId,
                        'issued',
                        $_SESSION['user_email'] ?? 'system',
                        'Bulk status update - selected invoices'
                    );
                    $updated++;
                }

                $_SESSION['success'] = "✅ Successfully marked {$updated} invoices as 'issued'";
                header('Location: ?page=invoices');
                exit;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get invoices that can be updated (not already issued/reconciled, not forecast)
$invoices = $db->fetchAll("
    SELECT i.*, le.legal_entity_name
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE (i.is_forecast = FALSE OR i.is_forecast IS NULL)
    AND i.invoice_status NOT IN ('issued', 'reconciled_to_xero')
    ORDER BY i.invoice_date DESC
");

require __DIR__ . '/../layouts/header.php';
?>

<div class="card">
    <h2>📋 Bulk Invoice Status Update</h2>
    
    <p style="margin-bottom: 20px; color: #666;">
        This tool allows you to mark multiple invoices as "issued" at once. 
        This is useful for cleaning up historic data where invoices were created but not marked as issued.
    </p>

    <?php if (empty($invoices)): ?>
        <div class="alert alert-info">
            ✅ All non-forecast invoices are already marked as 'issued' or 'reconciled_to_xero'. 
            No invoices need updating!
        </div>
        <a href="?page=invoices" class="btn btn-secondary">← Back to Invoices</a>
    <?php else: ?>
        <div class="alert alert-warning">
            <strong>⚠️ Found <?= count($invoices) ?> invoices</strong> that are currently in 'draft' or 'forecast' status.
        </div>

        <!-- Mark All Button -->
        <form method="POST" style="margin-bottom: 30px;" onsubmit="return confirm('Are you sure you want to mark ALL <?= count($invoices) ?> invoices as ISSUED? This cannot be undone.');">
            <input type="hidden" name="action" value="mark_all_issued">
            <button type="submit" class="btn btn-primary" style="font-size: 1.1em; padding: 12px 24px;">
                ✅ Mark All <?= count($invoices) ?> Invoices as "Issued"
            </button>
            <a href="?page=invoices" class="btn btn-secondary">Cancel</a>
        </form>

        <hr style="margin: 30px 0;">

        <!-- Select Individual Invoices -->
        <h3>Or Select Individual Invoices:</h3>
        <form method="POST" id="selectForm">
            <input type="hidden" name="action" value="mark_selected_issued">
            
            <div style="margin-bottom: 15px;">
                <button type="button" onclick="selectAll()" class="btn btn-secondary btn-sm">Select All</button>
                <button type="button" onclick="deselectAll()" class="btn btn-secondary btn-sm">Deselect All</button>
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Mark selected invoices as ISSUED?');">
                    ✅ Mark Selected as "Issued"
                </button>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th width="50"><input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)"></th>
                        <th>Invoice Number</th>
                        <th>Legal Entity</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Current Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="invoice_ids[]" value="<?= $invoice['id'] ?>" class="invoice-checkbox">
                            </td>
                            <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                            <td><?= htmlspecialchars($invoice['legal_entity_name']) ?></td>
                            <td><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></td>
                            <td>£<?= number_format($invoice['invoice_amount'], 2) ?></td>
                            <td>
                                <span class="badge badge-<?= $invoice['invoice_status'] === 'draft' ? 'secondary' : 'info' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $invoice['invoice_status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function selectAll() {
    document.querySelectorAll('.invoice-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
}

function deselectAll() {
    document.querySelectorAll('.invoice-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
}
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

