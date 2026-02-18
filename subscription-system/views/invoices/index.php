<?php
/**
 * Invoices Page
 */

use App\Models\Invoice;
use App\Database;
use App\Services\InvoiceReconciliationService;
use App\Services\InvoiceAutoGenerationService;

$pageTitle = 'Invoices';
$page = 'invoices';

$db = Database::getInstance();

// Check if recalculate is needed
$needsRecalculate = false;

// If we just recalculated (within last 5 seconds), skip checks to avoid stale data
$justRecalculated = isset($_SESSION['just_recalculated']) && (time() - $_SESSION['just_recalculated']) < 5;
if ($justRecalculated) {
    // Clear the flag after using it
    unset($_SESSION['just_recalculated']);
    $needsRecalculate = false;
} else {
    // Run the normal checks

// Check 1: Invoices missing forecasts
$missingForecasts = $db->fetchOne("
    SELECT COUNT(*) as count
    FROM invoices i
    WHERE (i.is_forecast = 0 OR i.is_forecast IS NULL)
    AND NOT EXISTS (
        SELECT 1 FROM invoices f
        WHERE f.parent_invoice_id = i.id
        AND f.is_forecast = 1
    )
")['count'] ?? 0;

// Check 2: Forecast invoices out of sync with parent
$outOfSyncForecasts = $db->fetchOne("
    SELECT COUNT(*) as count
    FROM invoices f
    JOIN invoices p ON f.parent_invoice_id = p.id
    WHERE f.is_forecast = 1
    AND f.invoice_amount != p.invoice_amount
")['count'] ?? 0;

// Check 3: Parent invoices where amount doesn't match allocated cameras
$outOfSyncParents = $db->fetchOne("
    SELECT COUNT(*) as count
    FROM invoices i
    LEFT JOIN (
        SELECT invoice_id, SUM(price_charged) as total
        FROM invoice_camera_allocations
        WHERE removed_date IS NULL
        GROUP BY invoice_id
    ) ica ON i.id = ica.invoice_id
    WHERE (i.is_forecast = 0 OR i.is_forecast IS NULL)
    AND (
        (ica.total IS NULL AND i.invoice_amount != 0) OR
        (ica.total IS NOT NULL AND ABS(i.invoice_amount - ica.total) > 0.01)
    )
")['count'] ?? 0;

$needsRecalculate = ($missingForecasts > 0 || $outOfSyncForecasts > 0 || $outOfSyncParents > 0);

} // End of else block for checks

// Check for forecast invoices due for conversion
$dueForecasts = $db->fetchOne("
    SELECT COUNT(*) as count
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE i.next_generation_date <= CURDATE()
    AND i.is_forecast = 1
    AND i.invoice_status = 'forecast'
    AND le.termination_date IS NULL
")['count'] ?? 0;

$filter = $_GET['filter'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$showForecast = isset($_GET['show_forecast']) && $_GET['show_forecast'] === '1';

// Build query with filters
$where = [];
$params = [];

// Forecast filter (hide by default)
if (!$showForecast) {
    $where[] = "i.is_forecast = FALSE";
}

// Status filter
if ($statusFilter !== 'all') {
    $where[] = "i.invoice_status = :status";
    $params['status'] = $statusFilter;
}

// Search filter
if (!empty($search)) {
    $where[] = "(i.invoice_number LIKE :search OR le.legal_entity_name LIKE :search)";
    $params['search'] = "%$search%";
}

// Payment status is managed in Xero - no filters needed here

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$invoices = $db->fetchAll("
    SELECT i.*,
           le.legal_entity_name,
           le.xero_company_name,
           COUNT(DISTINCT ica.camera_installation_id) as camera_count
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    LEFT JOIN invoice_camera_allocations ica ON i.id = ica.invoice_id AND ica.removed_date IS NULL
    $whereClause
    GROUP BY i.id
    ORDER BY i.invoice_date DESC
", $params);

// Get status counts for filter badges
$statusCounts = $db->fetchAll("
    SELECT invoice_status, COUNT(*) as count
    FROM invoices
    GROUP BY invoice_status
");
$counts = [];
foreach ($statusCounts as $row) {
    $counts[$row['invoice_status']] = $row['count'];
}

// Calculate summary statistics
$totalInvoices = count($db->fetchAll("SELECT id FROM invoices"));
$totalAmount = $db->fetchOne("SELECT SUM(invoice_amount) as total FROM invoices")['total'] ?? 0;
$draftCount = $counts['draft'] ?? 0;
$issuedCount = $counts['issued'] ?? 0;
$reconciledCount = $counts['reconciled_to_xero'] ?? 0;

require __DIR__ . '/../layouts/header.php';
?>

<style>
@keyframes pulse-green {
    0%, 100% {
        background-color: #27ae60;
        box-shadow: 0 0 0 0 rgba(39, 174, 96, 0.7);
    }
    50% {
        background-color: #2ecc71;
        box-shadow: 0 0 0 10px rgba(39, 174, 96, 0);
    }
}

.btn-recalculate-needed {
    animation: pulse-green 2s infinite;
    color: white !important;
    border: none !important;
    font-weight: bold;
}

.btn-recalculate-normal {
    background-color: #ff9800;
    color: white;
    border: none;
}
</style>

<!-- Summary Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 2em; font-weight: bold; color: #333;"><?= $totalInvoices ?></div>
        <div style="color: #666; margin-top: 5px;">Total Invoices</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 2em; font-weight: bold; color: #5cb85c;">£<?= number_format($totalAmount, 0) ?></div>
        <div style="color: #666; margin-top: 5px;">Total Value</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 2em; font-weight: bold; color: #999;"><?= $draftCount ?></div>
        <div style="color: #666; margin-top: 5px;">📝 Draft</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 2em; font-weight: bold; color: #5bc0de;"><?= $issuedCount ?></div>
        <div style="color: #666; margin-top: 5px;">📤 Issued</div>
    </div>
    <div class="card" style="text-align: center; padding: 20px;">
        <div style="font-size: 2em; font-weight: bold; color: #5cb85c;"><?= $reconciledCount ?></div>
        <div style="color: #666; margin-top: 5px;">✅ Reconciled</div>
    </div>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Invoices (<?= count($invoices) ?>)</h2>
        <div style="display: flex; gap: 10px;">
            <?php if ($dueForecasts > 0): ?>
            <a href="convert_forecasts.php" class="btn" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; border: none; animation: pulse 2s infinite;">
                🔄 Convert <?= $dueForecasts ?> Due Forecast<?= $dueForecasts != 1 ? 's' : '' ?>
            </a>
            <?php else: ?>
            <a href="convert_forecasts.php" class="btn" style="background: #6c757d; color: white; border: none;">
                🔄 Convert Forecasts
            </a>
            <?php endif; ?>
            <a href="?page=invoices&action=smart_match" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                🤖 Smart Match Invoices
            </a>
            <a href="?page=invoices&action=recalculate_all"
               class="btn <?= $needsRecalculate ? 'btn-recalculate-needed' : 'btn-recalculate-normal' ?>"
               onclick="return confirm('This will:\n1. Recalculate all invoice amounts based on allocated cameras\n2. Update all forecast invoices to match their parent invoices\n3. Generate missing forecast invoices through to 31/3/31\n\nContinue?')">
                <?= $needsRecalculate ? '⚠️ ' : '🔄 ' ?>Recalculate All & Generate Forecasts<?= $needsRecalculate ? ' (Action Needed!)' : '' ?>
            </a>
            <a href="?page=invoices&action=bulk_status_update" class="btn btn-info">✅ Bulk Mark as Issued</a>
            <a href="?page=invoices&action=bulk_reconcile" class="btn btn-success">🔄 Bulk Reconcile to Xero</a>
            <a href="?page=invoices&action=reset_all" class="btn btn-danger">🗑️ Reset All Invoices</a>
        </div>
    </div>

    <!-- Search Bar -->
    <div style="margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="page" value="invoices">
            <input type="text"
                   name="search"
                   placeholder="Search by invoice number or legal entity..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
            <button type="submit" class="btn btn-primary">🔍 Search</button>
            <?php if (!empty($search) || $statusFilter !== 'all' || $showForecast): ?>
                <a href="?page=invoices" class="btn">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Forecast Toggle -->
    <div style="margin-bottom: 20px; padding: 12px; background-color: #f8f9fa; border-radius: 4px;">
        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
            <input type="checkbox"
                   id="show_forecast"
                   <?= $showForecast ? 'checked' : '' ?>
                   onchange="toggleForecast(this.checked)"
                   style="width: 18px; height: 18px; cursor: pointer;">
            <span style="font-weight: 500;">
                🔮 Show Forecast Invoices
                <small style="color: #666; font-weight: normal;">(Future auto-renewals for reporting)</small>
            </span>
        </label>
    </div>

    <script>
    function toggleForecast(show) {
        const url = new URL(window.location);
        if (show) {
            url.searchParams.set('show_forecast', '1');
        } else {
            url.searchParams.delete('show_forecast');
        }
        window.location = url.toString();
    }
    </script>

    <!-- Status Filters -->
    <div style="margin-bottom: 20px;">
        <div style="margin-bottom: 10px; font-weight: bold; color: #666;">Filter by Status:</div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="?page=invoices&status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
               class="btn <?= $statusFilter === 'all' ? 'btn-success' : '' ?>">
                All Invoices
            </a>
            <a href="?page=invoices&status=draft<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
               class="btn <?= $statusFilter === 'draft' ? 'btn-success' : '' ?>">
                📝 Draft
                <?php if (isset($counts['draft'])): ?>
                    <span class="badge badge-secondary" style="margin-left: 5px;"><?= $counts['draft'] ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=invoices&status=issued<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
               class="btn <?= $statusFilter === 'issued' ? 'btn-success' : '' ?>">
                📤 Issued
                <?php if (isset($counts['issued'])): ?>
                    <span class="badge badge-secondary" style="margin-left: 5px;"><?= $counts['issued'] ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=invoices&status=reconciled_to_xero<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
               class="btn <?= $statusFilter === 'reconciled_to_xero' ? 'btn-success' : '' ?>">
                ✅ Reconciled
                <?php if (isset($counts['reconciled_to_xero'])): ?>
                    <span class="badge badge-secondary" style="margin-left: 5px;"><?= $counts['reconciled_to_xero'] ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=invoices&status=cancelled<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
               class="btn <?= $statusFilter === 'cancelled' ? 'btn-success' : '' ?>">
                ❌ Cancelled
                <?php if (isset($counts['cancelled'])): ?>
                    <span class="badge badge-secondary" style="margin-left: 5px;"><?= $counts['cancelled'] ?></span>
                <?php endif; ?>
            </a>
            <?php if ($showForecast): ?>
            <a href="?page=invoices&status=forecast&show_forecast=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
               class="btn <?= $statusFilter === 'forecast' ? 'btn-success' : '' ?>">
                🔮 Forecast
                <?php if (isset($counts['forecast'])): ?>
                    <span class="badge badge-secondary" style="margin-left: 5px;"><?= $counts['forecast'] ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment status is managed in Xero -->

    <?php if (empty($invoices)): ?>
        <p>No invoices found.
            <?php if (!empty($search) || $statusFilter !== 'all'): ?>
                <a href="?page=invoices">Clear filters</a> or
            <?php endif; ?>
            <a href="?page=import&type=xero">Import from Xero</a>.
        </p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Status</th>
                    <th>Legal Entity</th>
                    <th>Frequency</th>
                    <th>Invoice Date</th>
                    <th>Cameras</th>
                    <th>Amount</th>
                    <th>Next Generation</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice):
                    // Determine invoice status badge
                    $statusBadge = '';
                    $statusClass = '';
                    switch ($invoice['invoice_status']) {
                        case 'draft':
                            $statusBadge = '📝 Draft';
                            $statusClass = 'badge-secondary';
                            break;
                        case 'issued':
                            $statusBadge = '📤 Issued';
                            $statusClass = 'badge-info';
                            break;
                        case 'reconciled_to_xero':
                            $statusBadge = '✅ Reconciled';
                            $statusClass = 'badge-success';
                            break;
                        case 'cancelled':
                            $statusBadge = '❌ Cancelled';
                            $statusClass = 'badge-danger';
                            break;
                        case 'merged':
                            $statusBadge = '🔗 Merged';
                            $statusClass = 'badge-warning';
                            break;
                        case 'forecast':
                            $statusBadge = '🔮 Forecast';
                            $statusClass = 'badge-forecast';
                            break;
                        default:
                            $statusBadge = 'Unknown';
                            $statusClass = 'badge-secondary';
                    }
                ?>
                <tr style="<?= $invoice['invoice_status'] === 'cancelled' ? 'opacity: 0.6;' : '' ?><?= $invoice['is_forecast'] ? 'background-color: #f0f8ff;' : '' ?>">
                    <td>
                        <strong><a href="?page=invoices&action=view&id=<?= $invoice['id'] ?>"><?= htmlspecialchars($invoice['invoice_number']) ?></a></strong>
                        <?php if ($invoice['is_auto_generated']): ?>
                            <span class="badge badge-info" style="font-size: 0.75em; margin-left: 5px;">🤖 Auto</span>
                        <?php endif; ?>
                        <?php if ($invoice['is_forecast']): ?>
                            <span class="badge badge-forecast" style="font-size: 0.75em; margin-left: 5px;">🔮 Period <?= $invoice['forecast_year'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $statusClass ?>"><?= $statusBadge ?></span>
                    </td>
                    <td>
                        <a href="?page=subscribers&action=view&id=<?= $invoice['legal_entity_id'] ?>">
                            <?= htmlspecialchars($invoice['legal_entity_name']) ?>
                        </a>
                    </td>
                    <td>
                        <?php
                        $frequency = $invoice['payment_frequency'] ?? 'N/A';
                        $frequencyBadge = '';
                        $frequencyColor = '';
                        switch (strtolower($frequency)) {
                            case 'monthly':
                                $frequencyBadge = '📅 Monthly';
                                $frequencyColor = '#4caf50';
                                break;
                            case 'quarterly':
                                $frequencyBadge = '📊 Quarterly';
                                $frequencyColor = '#2196f3';
                                break;
                            case 'annual':
                            case 'annually':
                                $frequencyBadge = '📆 Annual';
                                $frequencyColor = '#ff9800';
                                break;
                            default:
                                $frequencyBadge = ucfirst($frequency);
                                $frequencyColor = '#999';
                        }
                        ?>
                        <span style="background-color: <?= $frequencyColor ?>; color: white; padding: 4px 10px; border-radius: 12px; font-weight: 500; font-size: 0.85em; white-space: nowrap;">
                            <?= $frequencyBadge ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></td>
                    <td style="text-align: center;">
                        <?php if ($invoice['camera_count'] > 0): ?>
                            <span style="background-color: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-weight: 500; font-size: 0.9em;">
                                📹 <?= $invoice['camera_count'] ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><strong>£<?= number_format($invoice['invoice_amount'], 2) ?></strong></td>
                    <td>
                        <?php if ($invoice['next_generation_date']): ?>
                            <?= date('d/m/Y', strtotime($invoice['next_generation_date'])) ?>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=invoices&action=view&id=<?= $invoice['id'] ?>" class="btn btn-sm">View</a>
                        <?php if ($invoice['invoice_status'] === 'draft'): ?>
                            <a href="?page=invoices&action=allocate&id=<?= $invoice['id'] ?>" class="btn btn-primary btn-sm">📹 Cameras</a>
                            <a href="?page=invoices&action=delete&id=<?= $invoice['id'] ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete invoice <?= htmlspecialchars($invoice['invoice_number']) ?>? This action cannot be undone.')">
                                🗑️
                            </a>
                        <?php elseif ($invoice['invoice_status'] === 'forecast'): ?>
                            <a href="?page=invoices&action=delete&id=<?= $invoice['id'] ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete forecast invoice <?= htmlspecialchars($invoice['invoice_number']) ?>?\n\nThis will free up the cameras allocated to this invoice.')">
                                🗑️ Delete
                            </a>
                        <?php elseif ($invoice['invoice_status'] === 'reconciled_to_xero'): ?>
                            <span style="color: #999; font-size: 0.85em;">Locked</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

