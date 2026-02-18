<?php
/**
 * Bulk Reconciliation Tool
 * Reconcile multiple invoices to Xero at once
 */

use App\Database;
use App\Services\InvoiceStatusService;

$pageTitle = 'Bulk Reconcile to Xero';
$page = 'invoices';

$db = Database::getInstance();
$statusService = new InvoiceStatusService();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reconciliations = $_POST['reconciliations'] ?? [];
    $results = [
        'success' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    foreach ($reconciliations as $invoiceId => $data) {
        // Skip if no Xero details provided
        if (empty($data['xero_id']) || empty($data['xero_number'])) {
            continue;
        }
        
        try {
            $statusService->reconcileToXero(
                $invoiceId,
                $data['xero_id'],
                $data['xero_number'],
                $_SESSION['user_email'] ?? 'system'
            );
            $results['success']++;
        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = "Invoice ID $invoiceId: " . $e->getMessage();
        }
    }
    
    if ($results['success'] > 0) {
        $_SESSION['success'] = "Successfully reconciled {$results['success']} invoice(s)!";
    }
    if ($results['failed'] > 0) {
        $_SESSION['error'] = "Failed to reconcile {$results['failed']} invoice(s). " . implode('; ', $results['errors']);
    }
    
    header('Location: ?page=invoices&action=bulk_reconcile');
    exit;
}

// Get all issued invoices (ready for reconciliation)
$invoices = $db->fetchAll("
    SELECT i.*, le.legal_entity_name, le.xero_company_name
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE i.invoice_status = 'issued'
    ORDER BY i.invoice_date DESC
");

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>🔄 Bulk Reconcile to Xero</h1>
        <a href="?page=invoices" class="btn">← Back to Invoices</a>
    </div>

    <?php if (empty($invoices)): ?>
        <div class="card">
            <p>No issued invoices found. Only invoices with status "Issued" can be reconciled.</p>
            <p><a href="?page=invoices" class="btn btn-primary">View All Invoices</a></p>
        </div>
    <?php else: ?>
        <div class="card" style="margin-bottom: 20px; background: #e7f3ff; border-left: 4px solid #2196F3;">
            <h3 style="margin-top: 0;">📋 Instructions</h3>
            <ol style="margin-bottom: 0;">
                <li>For each invoice below, enter the <strong>Xero Invoice ID</strong> and <strong>Xero Invoice Number</strong></li>
                <li>You can find these in Xero:
                    <ul>
                        <li><strong>Xero Invoice ID</strong>: In the URL when viewing the invoice (long UUID)</li>
                        <li><strong>Xero Invoice Number</strong>: The invoice number shown on the page (e.g., INV-2026-001)</li>
                    </ul>
                </li>
                <li>Leave fields blank for invoices you don't want to reconcile</li>
                <li>Click "Reconcile Selected Invoices" when ready</li>
            </ol>
        </div>

        <form method="POST" onsubmit="return confirmBulkReconcile()">
            <div class="card">
                <h2>Issued Invoices (<?= count($invoices) ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Xero Company Name</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th style="width: 250px;">Xero Invoice ID</th>
                            <th style="width: 150px;">Xero Invoice Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                            <td><?= htmlspecialchars($invoice['xero_company_name'] ?? $invoice['legal_entity_name']) ?></td>
                            <td><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></td>
                            <td>£<?= number_format($invoice['invoice_amount'], 2) ?></td>
                            <td>
                                <input type="text" 
                                       name="reconciliations[<?= $invoice['id'] ?>][xero_id]" 
                                       placeholder="a1b2c3d4-e5f6-7890..."
                                       style="width: 100%; padding: 5px;">
                            </td>
                            <td>
                                <input type="text" 
                                       name="reconciliations[<?= $invoice['id'] ?>][xero_number]" 
                                       placeholder="INV-2026-001"
                                       style="width: 100%; padding: 5px;">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button type="submit" class="btn btn-success" style="font-size: 1.1em; padding: 12px 24px;">
                    ✅ Reconcile Selected Invoices
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function confirmBulkReconcile() {
    // Count how many invoices have data entered
    let count = 0;
    const inputs = document.querySelectorAll('input[name*="[xero_id]"]');
    inputs.forEach(input => {
        if (input.value.trim() !== '') {
            count++;
        }
    });
    
    if (count === 0) {
        alert('Please enter Xero details for at least one invoice.');
        return false;
    }
    
    return confirm(`Reconcile ${count} invoice(s) to Xero?\n\nThis will lock these invoices and prevent further edits.`);
}
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

