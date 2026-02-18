<?php
/**
 * Reconciliation Dashboard
 * Monthly invoice reconciliation workflow
 */

use App\Database;
use App\Services\InvoiceReconciliationService;

$pageTitle = 'Invoice Reconciliation Dashboard';
$page = 'reconciliation';

$db = Database::getInstance();
$reconciliationService = new InvoiceReconciliationService();

// Get selected month (default to current month)
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Handle reconcile all action
if (isset($_GET['action']) && $_GET['action'] === 'reconcile_all') {
    $result = $reconciliationService->reconcileAll();
    $_SESSION['success'] = "Reconciled {$result['total']} invoices: {$result['matched']} matched, {$result['under_charged']} under-charged, {$result['over_charged']} over-charged";
    header('Location: ?page=reconciliation&month=' . $selectedMonth);
    exit;
}

// Get invoices for selected month
$invoices = $db->fetchAll(
    "SELECT i.*, le.legal_entity_name, le.xero_company_name,
        (SELECT COUNT(*) FROM invoice_camera_allocations WHERE invoice_id = i.id) as allocation_count
     FROM invoices i
     JOIN legal_entities le ON i.legal_entity_id = le.id
     WHERE DATE_FORMAT(i.invoice_date, '%Y-%m') = :month
     ORDER BY i.invoice_date DESC, le.legal_entity_name",
    ['month' => $selectedMonth]
);

// Calculate summary statistics
$stats = [
    'total' => count($invoices),
    'matched' => 0,
    'under_charged' => 0,
    'over_charged' => 0,
    'pending' => 0,
    'total_variance' => 0,
    'allocated' => 0,
    'not_allocated' => 0
];

foreach ($invoices as $invoice) {
    $status = $invoice['reconciliation_status'] ?? 'pending';
    $stats[$status]++;

    if ($invoice['variance'] !== null) {
        $stats['total_variance'] += $invoice['variance'];
    }

    if ($invoice['allocation_count'] > 0) {
        $stats['allocated']++;
    } else {
        $stats['not_allocated']++;
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Invoice Reconciliation Dashboard</h1>
        <div style="display: flex; gap: 10px;">
            <a href="?page=reconciliation&action=reconcile_all&month=<?= $selectedMonth ?>"
               class="btn btn-success"
               onclick="return confirm('Reconcile all invoices for <?= date('F Y', strtotime($selectedMonth . '-01')) ?>?')">
                🔄 Reconcile All
            </a>
        </div>
    </div>

    <!-- Month Selector -->
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="page" value="reconciliation">
            <label for="month" style="margin: 0; font-weight: bold;">Select Month:</label>
            <input type="month" id="month" name="month" value="<?= $selectedMonth ?>"
                   onchange="this.form.submit()" style="padding: 8px;">
            <button type="submit" class="btn">Go</button>
        </form>
    </div>

    <!-- Summary Statistics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div class="card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2em; font-weight: bold; color: #333;"><?= $stats['total'] ?></div>
            <div style="color: #666;">Total Invoices</div>
        </div>
        <div class="card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2em; font-weight: bold; color: #5cb85c;"><?= $stats['matched'] ?></div>
            <div style="color: #666;">✓ Matched</div>
        </div>
        <div class="card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2em; font-weight: bold; color: #f0ad4e;"><?= $stats['under_charged'] ?></div>
            <div style="color: #666;">⚠ Under Charged</div>
        </div>
        <div class="card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2em; font-weight: bold; color: #d9534f;"><?= $stats['over_charged'] ?></div>
            <div style="color: #666;">⚠ Over Charged</div>
        </div>
        <div class="card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2em; font-weight: bold; color: #999;"><?= $stats['pending'] ?></div>
            <div style="color: #666;">Pending</div>
        </div>
        <div class="card" style="text-align: center; padding: 20px;">
            <div style="font-size: 1.5em; font-weight: bold; color: <?= $stats['total_variance'] > 0 ? '#d9534f' : ($stats['total_variance'] < 0 ? '#f0ad4e' : '#5cb85c') ?>;">
                <?= $stats['total_variance'] > 0 ? '+' : '' ?>£<?= number_format($stats['total_variance'], 2) ?>
            </div>
            <div style="color: #666;">Total Variance</div>
        </div>
    </div>

    <!-- Allocation Status -->
    <?php if ($stats['not_allocated'] > 0): ?>
    <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 20px;">
        <strong>⚠ Action Required:</strong> <?= $stats['not_allocated'] ?> invoice(s) do not have camera allocations.
        <a href="#not-allocated" style="margin-left: 10px;">View →</a>
    </div>
    <?php endif; ?>

    <!-- Invoice List -->
    <div class="card">
        <h2>Invoices for <?= date('F Y', strtotime($selectedMonth . '-01')) ?></h2>

        <?php if (empty($invoices)): ?>
            <p>No invoices found for this month.</p>
        <?php else: ?>


            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Legal Entity</th>
                        <th>Xero Company</th>
                        <th>Date</th>
                        <th>Actual</th>
                        <th>Expected</th>
                        <th>Variance</th>
                        <th>Status</th>
                        <th>Allocated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice):
                        $reconStatus = $invoice['reconciliation_status'] ?? 'pending';
                        $reconBadge = '';
                        $reconClass = '';

                        switch ($reconStatus) {
                            case 'matched':
                                $reconBadge = '✓ Matched';
                                $reconClass = 'badge-success';
                                break;
                            case 'under_charged':
                                $reconBadge = '⚠ Under';
                                $reconClass = 'badge-danger';
                                break;
                            case 'over_charged':
                                $reconBadge = '⚠ Over';
                                $reconClass = 'badge-warning';
                                break;
                            default:
                                $reconBadge = 'Pending';
                                $reconClass = 'badge-secondary';
                        }

                        $hasAllocations = $invoice['allocation_count'] > 0;
                    ?>
                    <tr <?= !$hasAllocations ? 'id="not-allocated" style="background: #fff3cd;"' : '' ?>>
                        <td><strong><a href="?page=invoices&action=view&id=<?= $invoice['id'] ?>"><?= htmlspecialchars($invoice['invoice_number']) ?></a></strong></td>
                        <td><?= htmlspecialchars($invoice['legal_entity_name']) ?></td>
                        <td><?= htmlspecialchars($invoice['xero_company_name'] ?? '-') ?></td>
                        <td><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></td>
                        <td><strong>£<?= number_format($invoice['invoice_amount'], 2) ?></strong></td>
                        <td>
                            <?php if ($invoice['expected_amount']): ?>
                                £<?= number_format($invoice['expected_amount'], 2) ?>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($invoice['variance'] !== null): ?>
                                <span style="color: <?= abs($invoice['variance']) <= 1 ? '#5cb85c' : ($invoice['variance'] < 0 ? '#f0ad4e' : '#d9534f') ?>;">
                                    <?= $invoice['variance'] > 0 ? '+' : '' ?>£<?= number_format(abs($invoice['variance']), 2) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $reconClass ?>"><?= $reconBadge ?></span></td>
                        <td>
                            <?php if ($hasAllocations): ?>
                                <span class="badge badge-success">✓ Yes</span>
                            <?php else: ?>
                                <span class="badge badge-warning">⚠ No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?page=invoices&action=view&id=<?= $invoice['id'] ?>" class="btn btn-sm">View</a>
                            <?php if (!$hasAllocations): ?>
                                <a href="?page=invoices&action=allocate&id=<?= $invoice['id'] ?>" class="btn btn-success btn-sm">Allocate</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
