<?php
/**
 * Dashboard
 * UPDATED: Now uses Legal Entities instead of Subscribers
 */

use App\Models\LegalEntity;
use App\Models\Invoice;
use App\Services\PrepaymentCalculator;
use App\Services\InvoiceReconciliationService;
use App\Database;

$pageTitle = 'Dashboard';
$page = 'dashboard';

// Get statistics
$legalEntityModel = new LegalEntity();
$invoiceModel = new Invoice();
$prepaymentCalc = new PrepaymentCalculator();
$reconciliationService = new InvoiceReconciliationService();
$db = Database::getInstance();

$totalLegalEntities = count($legalEntityModel->all());
$totalRevenue = $invoiceModel->getTotalRevenue();

// Calculate total prepayments
$allInvoices = $invoiceModel->all();
$totalPrepayments = $prepaymentCalc->getTotalPrepayments($allInvoices);

// Get reconciliation issues using the NEW InvoiceReconciliationService
// Exclude forecast invoices - they're not real invoices yet
$allInvoices = $db->fetchAll(
    "SELECT i.*, le.legal_entity_name,
        (SELECT COUNT(*) FROM invoice_camera_allocations WHERE invoice_id = i.id) as allocation_count
     FROM invoices i
     JOIN legal_entities le ON i.legal_entity_id = le.id
     WHERE (i.is_forecast = FALSE OR i.is_forecast IS NULL)
     ORDER BY i.invoice_date DESC"
);

$issues = [];
foreach ($allInvoices as $invoice) {
    // Calculate expected amount from allocations
    $expectedAmount = $reconciliationService->calculateExpectedAmount($invoice['id']);
    $variance = $invoice['invoice_amount'] - $expectedAmount;

    // Determine if this is an issue (variance > £1 or no allocations)
    $hasVariance = abs($variance) > 1.00;
    $hasNoAllocations = $invoice['allocation_count'] == 0;

    if ($hasVariance || $hasNoAllocations) {
        $status = 'installation_mismatch';
        if ($hasNoAllocations) {
            $status = 'not_allocated';
        } elseif ($hasVariance) {
            $status = $variance < 0 ? 'under_charged' : 'over_charged';
        }

        $issues[] = [
            'invoice_number' => $invoice['invoice_number'],
            'legal_entity_name' => $invoice['legal_entity_name'],
            'invoice_date' => $invoice['invoice_date'],
            'actual_amount' => $invoice['invoice_amount'],
            'expected_amount' => $expectedAmount,
            'amount_variance' => $variance,
            'status' => $status
        ];
    }
}

// Camera snapshots table was removed in Migration 023
// We now use camera_installations table for tracking cameras
$mismatchedCameras = [];

require __DIR__ . '/../layouts/header.php';
?>

<div class="stats">
    <div class="stat-card">
        <h3>Total Legal Entities</h3>
        <div class="value"><?= number_format($totalLegalEntities) ?></div>
    </div>

    <div class="stat-card">
        <h3>Total Revenue (All Time)</h3>
        <div class="value">£<?= number_format($totalRevenue, 2) ?></div>
    </div>

    <div class="stat-card">
        <h3>Current Prepayments</h3>
        <div class="value">£<?= number_format($totalPrepayments, 2) ?></div>
    </div>
</div>

<?php if (count($issues) > 0): ?>
<div class="card">
    <h2>⚠️ Reconciliation Issues (<?= count($issues) ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Legal Entity</th>
                <th>Date</th>
                <th>Actual Amount</th>
                <th>Expected Amount</th>
                <th>Variance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($issues, 0, 10) as $issue): ?>
            <tr>
                <td><?= htmlspecialchars($issue['invoice_number']) ?></td>
                <td><?= htmlspecialchars($issue['legal_entity_name']) ?></td>
                <td><?= date('d/m/Y', strtotime($issue['invoice_date'])) ?></td>
                <td>£<?= number_format($issue['actual_amount'], 2) ?></td>
                <td>£<?= number_format($issue['expected_amount'], 2) ?></td>
                <td style="color: <?= $issue['amount_variance'] < 0 ? '#e74c3c' : '#f39c12' ?>">
                    £<?= number_format($issue['amount_variance'], 2) ?>
                </td>
                <td>
                    <?php if ($issue['status'] === 'under_charged'): ?>
                        <span class="badge badge-danger">Under Charged</span>
                    <?php elseif ($issue['status'] === 'over_charged'): ?>
                        <span class="badge badge-warning">Over Charged</span>
                    <?php else: ?>
                        <span class="badge badge-info">Installation Mismatch</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (count($issues) > 10): ?>
        <p style="margin-top: 15px;"><a href="?page=reports&report=reconciliation" class="btn">View All Issues</a></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>Quick Actions</h2>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="?page=import&type=xero" class="btn">Import Xero Invoices</a>
        <a href="?page=import&type=cameras" class="btn">Import Camera Data</a>
        <a href="?page=subscribers&action=new" class="btn btn-success">Add New Subscriber</a>
        <a href="?page=reports&report=prepayments" class="btn">View Prepayments Report</a>
        <a href="?page=reports&report=cashflow" class="btn">Cash Flow Forecast</a>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

