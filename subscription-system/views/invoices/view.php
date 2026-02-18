<?php
/**
 * Invoice Detail View
 * Shows invoice details with camera breakdown and reconciliation information
 */

use App\Database;
use App\Services\CameraAllocationService;
use App\Services\InvoiceReconciliationService;

$pageTitle = 'Invoice Details';
$page = 'invoices';

$invoiceId = $_GET['id'] ?? null;
$db = Database::getInstance();

if (!$invoiceId) {
    header('Location: ?page=invoices');
    exit;
}

// Handle reconcile action
if (isset($_GET['action']) && $_GET['action'] === 'reconcile') {
    $reconciliationService = new InvoiceReconciliationService();

    // DEBUG: Log what we're calculating
    error_log("=== RECONCILE DEBUG ===");
    error_log("Invoice ID: " . $invoiceId);

    $calculatedAmount = $reconciliationService->calculateExpectedAmount($invoiceId);
    error_log("Calculated Expected Amount: " . $calculatedAmount);

    $result = $reconciliationService->reconcileInvoice($invoiceId);
    error_log("Reconcile Result: " . json_encode($result));

    if (isset($result['error'])) {
        $_SESSION['error'] = $result['error'];
    } else {
        $_SESSION['success'] = 'Invoice reconciled successfully! Calculated: £' . number_format($calculatedAmount, 2) . ' | Expected: £' . number_format($result['expected_amount'], 2) . ', Variance: £' . number_format($result['variance'], 2);
    }

    header('Location: ?page=invoices&action=view&id=' . $invoiceId);
    exit;
}

// Get invoice with legal entity details
$invoice = $db->fetchOne(
    "SELECT i.*, le.id AS legal_entity_pk, le.legal_entity_name, le.xero_company_name, le.payment_terms_days
     FROM invoices i
     JOIN legal_entities le ON i.legal_entity_id = le.id
     WHERE i.id = :id",
    ['id' => $invoiceId]
);

if (!$invoice) {
    $_SESSION['error'] = 'Invoice not found';
    header('Location: ?page=invoices');
    exit;
}

// Get camera allocations for this invoice
// Note: The new invoice generation system stores individual camera records
// We need to aggregate them by store for display
$db_allocations = $db->fetchAll(
    "SELECT
        ica.*,
        s.store_name,
        s.store_id as store_code,
        ci.installation_date,
        ica.camera_type
     FROM invoice_camera_allocations ica
     JOIN stores s ON ica.store_id = s.id
     LEFT JOIN camera_installations ci ON ica.camera_installation_id = ci.id
     WHERE ica.invoice_id = :invoice_id
     ORDER BY s.store_name, ica.camera_type",
    ['invoice_id' => $invoiceId]
);

// Aggregate cameras by store
$allocations = [];
$storeGroups = [];

foreach ($db_allocations as $camera) {
    $storeId = $camera['store_id'];

    if (!isset($storeGroups[$storeId])) {
        $storeGroups[$storeId] = [
            'store_id' => $storeId,
            'store_name' => $camera['store_name'],
            'store_code' => $camera['store_code'],
            'main_cameras' => 0,
            'additional_cameras' => 0,
            'cameras' => [],
            'subtotal' => 0
        ];
    }

    // Count cameras by type
    if ($camera['camera_type'] === 'main') {
        $storeGroups[$storeId]['main_cameras']++;
    } else {
        $storeGroups[$storeId]['additional_cameras']++;
    }

    // Add to subtotal
    $storeGroups[$storeId]['subtotal'] += $camera['price_charged'];

    // Keep individual camera records
    $storeGroups[$storeId]['cameras'][] = $camera;
}

// Convert to indexed array
$allocations = array_values($storeGroups);

// Calculate totals
$totalMainCameras = 0;
$totalAdditionalCameras = 0;
$totalSubtotal = 0;

foreach ($allocations as $allocation) {
    $totalMainCameras += $allocation['main_cameras'];
    $totalAdditionalCameras += $allocation['additional_cameras'];
    $totalSubtotal += $allocation['subtotal'];
}

// Get individual camera list for detailed view
$individualCameras = $db_allocations;

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
        $statusBadge = '✅ Reconciled to Xero';
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

// Determine reconciliation status badge
$reconBadge = '';
$reconClass = '';
switch ($invoice['reconciliation_status'] ?? 'pending') {
    case 'matched':
        $reconBadge = '✓ Matched';
        $reconClass = 'badge-success';
        break;
    case 'under_charged':
        $reconBadge = '⚠ Under Charged';
        $reconClass = 'badge-danger';
        break;
    case 'over_charged':
        $reconBadge = '⚠ Over Charged';
        $reconClass = 'badge-warning';
        break;
    default:
        $reconBadge = 'Pending';
        $reconClass = 'badge-secondary';
}

// Check if invoice can be edited (only draft status)
$canEdit = ($invoice['invoice_status'] === 'draft');

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1>Invoice: <?= htmlspecialchars($invoice['invoice_number']) ?></h1>
            <div style="margin-top: 10px;">
                <span class="badge <?= $statusClass ?>" style="font-size: 1.1em; padding: 8px 12px;">
                    <?= $statusBadge ?>
                </span>
                <?php if ($invoice['is_auto_generated']): ?>
                    <span class="badge badge-info" style="font-size: 0.9em; padding: 6px 10px; margin-left: 8px;">
                        🤖 Auto-Generated
                    </span>
                <?php endif; ?>
                <?php if ($invoice['is_forecast']): ?>
                    <span class="badge badge-forecast" style="font-size: 0.9em; padding: 6px 10px; margin-left: 8px;">
                        🔮 Forecast Period <?= $invoice['forecast_year'] ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="?page=invoices&action=cluster&legal_entity_id=<?= htmlspecialchars($invoice['legal_entity_pk']) ?>&invoice_id=<?= htmlspecialchars($invoice['id']) ?>"
               class="btn btn-primary">
                📅 View Timeline
            </a>
            <a href="?page=invoices&action=reconcile&id=<?= $invoiceId ?>"
               class="btn btn-success"
               onclick="return confirm('Recalculate expected amount for this invoice?')">
                🔄 Recalculate Expected Amount
            </a>
            <a href="?page=invoices" class="btn">← Back to Invoices</a>
        </div>
    </div>

    <!-- Invoice Header -->
    <div class="card" style="margin-bottom: 20px;">
        <h2>Invoice Information</h2>
        <table style="width: 100%;">
            <tr>
                <td style="width: 200px; font-weight: bold;">Invoice Number:</td>
                <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Legal Entity:</td>
                <td>
                    <a href="?page=subscribers&action=view&id=<?= $invoice['legal_entity_id'] ?>">
                        <?= htmlspecialchars($invoice['legal_entity_name']) ?>
                    </a>
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Xero Customer Number:</td>
                <td><?= htmlspecialchars($invoice['xero_customer_number'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Invoice Date:</td>
                <td>
                    <span id="invoiceDateDisplay"><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></span>
                    <?php if ($canEdit): ?>
                        <button onclick="toggleEditDates()" class="btn btn-sm btn-secondary" style="margin-left: 10px;">
                            ✏️ Edit
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($invoice['due_date']): ?>
            <tr>
                <td style="font-weight: bold;">Due Date:</td>
                <td>
                    <span id="dueDateDisplay"><?= date('d/m/Y', strtotime($invoice['due_date'])) ?></span>
                </td>
            </tr>
            <?php endif; ?>

            <tr>
                <td style="font-weight: bold;">Payment Frequency:</td>
                <td><span class="badge badge-info"><?= ucfirst($invoice['payment_frequency']) ?></span></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Invoice Status:</td>
                <td><span class="badge <?= $statusClass ?>"><?= $statusBadge ?></span></td>
            </tr>
            <?php if ($invoice['is_auto_generated']): ?>
            <tr>
                <td style="font-weight: bold;">Auto-Generated:</td>
                <td>
                    <span class="badge badge-info">Yes</span>
                    <?php if ($invoice['parent_invoice_id']): ?>
                        <?php
                        $parentInvoice = $db->fetchOne(
                            "SELECT invoice_number FROM invoices WHERE id = :id",
                            ['id' => $invoice['parent_invoice_id']]
                        );
                        ?>
                        - From <a href="?page=invoices&action=view&id=<?= $invoice['parent_invoice_id'] ?>">
                            <?= htmlspecialchars($parentInvoice['invoice_number']) ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($invoice['next_generation_date']): ?>
            <tr>
                <td style="font-weight: bold;">Next Generation Date:</td>
                <td>
                    <?= date('d/m/Y', strtotime($invoice['next_generation_date'])) ?>
                    <span style="color: #999; font-size: 0.9em;">
                        (<?= ucfirst($invoice['payment_frequency']) ?> renewal)
                    </span>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($invoice['invoice_status'] === 'reconciled_to_xero'): ?>
            <tr>
                <td style="font-weight: bold;">Xero Invoice ID:</td>
                <td><?= htmlspecialchars($invoice['xero_invoice_id'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Xero Invoice Number:</td>
                <td><?= htmlspecialchars($invoice['xero_invoice_number'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Reconciled Date:</td>
                <td>
                    <?php if ($invoice['reconciled_date']): ?>
                        <?= date('d/m/Y', strtotime($invoice['reconciled_date'])) ?>
                        by <?= htmlspecialchars($invoice['reconciled_by']) ?>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Edit Dates Form (hidden by default) -->
    <?php if ($canEdit): ?>
    <div id="editDatesForm" class="card" style="display: none; margin-bottom: 20px; background: #fff3cd;">
        <h2>✏️ Edit Invoice Dates</h2>
        <form method="POST" action="?page=invoices&action=update_dates&id=<?= $invoiceId ?>">
            <div style="margin-bottom: 15px;">
                <label for="invoice_date" style="display: block; font-weight: bold; margin-bottom: 5px;">
                    Invoice Date:
                </label>
                <input type="date"
                       id="invoice_date"
                       name="invoice_date"
                       value="<?= $invoice['invoice_date'] ?>"
                       required
                       style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label for="due_date" style="display: block; font-weight: bold; margin-bottom: 5px;">
                    Due Date (optional):
                </label>
                <input type="date"
                       id="due_date"
                       name="due_date"
                       value="<?= $invoice['due_date'] ?? '' ?>"
                       style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px;">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">
                    💾 Save Changes
                </button>
                <button type="button" onclick="toggleEditDates()" class="btn btn-secondary">
                    ❌ Cancel
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Status Actions -->
    <?php if ($invoice['invoice_status'] !== 'cancelled' && $invoice['invoice_status'] !== 'merged'): ?>
    <div class="card" style="margin-bottom: 20px; background: #f8f9fa;">
        <h2>Invoice Actions</h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if ($invoice['invoice_status'] === 'draft'): ?>
                <a href="?page=invoices&action=allocate&id=<?= $invoiceId ?>" class="btn btn-primary">
                    ✏️ Edit Cameras
                </a>
                <button onclick="markAsIssued()" class="btn btn-success">
                    📤 Mark as Issued
                </button>
                <button onclick="cancelInvoice()" class="btn btn-danger">
                    ❌ Cancel Invoice
                </button>
            <?php elseif ($invoice['invoice_status'] === 'issued'): ?>
                <a href="?page=invoices&action=reconcile_form&id=<?= $invoiceId ?>" class="btn btn-success">
                    ✅ Reconcile to Xero
                </a>
                <button onclick="cancelInvoice()" class="btn btn-danger">
                    ❌ Cancel Invoice
                </button>
            <?php elseif ($invoice['invoice_status'] === 'reconciled_to_xero'): ?>
                <div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
                    <strong>✅ This invoice is reconciled to Xero and cannot be edited.</strong>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function markAsIssued() {
        if (confirm('Mark this invoice as Issued?\n\nThis means the invoice has been sent to the customer. You will not be able to edit it after this.')) {
            window.location.href = 'index.php?page=invoices&action=change_status&id=<?= $invoiceId ?>&status=issued';
        }
    }

    function reconcileToXero() {
        const xeroInvoiceId = prompt('Enter Xero Invoice ID:');
        if (!xeroInvoiceId) return;

        const xeroInvoiceNumber = prompt('Enter Xero Invoice Number:');
        if (!xeroInvoiceNumber) return;

        if (confirm('Reconcile this invoice to Xero?\n\nXero ID: ' + xeroInvoiceId + '\nXero Number: ' + xeroInvoiceNumber + '\n\nThis will lock the invoice and prevent further edits.')) {
            window.location.href = 'index.php?page=invoices&action=reconcile_to_xero&id=<?= $invoiceId ?>&xero_id=' + encodeURIComponent(xeroInvoiceId) + '&xero_number=' + encodeURIComponent(xeroInvoiceNumber);
        }
    }

    function cancelInvoice() {
        const reason = prompt('Enter reason for cancellation:');
        if (!reason) return;

        if (confirm('Cancel this invoice?\n\nThis action cannot be undone.')) {
            window.location.href = 'index.php?page=invoices&action=change_status&id=<?= $invoiceId ?>&status=cancelled&notes=' + encodeURIComponent(reason);
        }
    }

    function toggleEditDates() {
        const form = document.getElementById('editDatesForm');
        if (form.style.display === 'none') {
            form.style.display = 'block';
            // Scroll to the form
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            form.style.display = 'none';
        }
    }
    </script>

    <!-- Reconciliation Summary (only shown for reconciled invoices with Xero amount) -->
    <?php if ($invoice['invoice_status'] === 'reconciled_to_xero' && isset($invoice['xero_invoice_amount']) && $invoice['xero_invoice_amount'] !== null): ?>
    <?php
        // Calculate variance: System Amount - Xero Amount
        $xeroVariance = $invoice['invoice_amount'] - $invoice['xero_invoice_amount'];
        $variancePercent = $invoice['xero_invoice_amount'] > 0
            ? ($xeroVariance / $invoice['xero_invoice_amount']) * 100
            : 0;
    ?>
    <div class="card" style="margin-bottom: 20px;">
        <h2>Xero Reconciliation</h2>
        <table style="width: 100%;">
            <tr>
                <td style="width: 250px; font-weight: bold;">System Invoice Amount:</td>
                <td style="font-size: 1.2em;"><strong>£<?= number_format($invoice['invoice_amount'], 2) ?></strong></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Xero Invoice Amount:</td>
                <td style="font-size: 1.2em;">
                    <strong>£<?= number_format($invoice['xero_invoice_amount'], 2) ?></strong>
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Variance:</td>
                <td style="font-size: 1.2em;">
                    <?php if (abs($xeroVariance) <= 0.01): ?>
                        <strong style="color: #5cb85c;">
                            ✓ Matched
                        </strong>
                    <?php else: ?>
                        <strong style="color: <?= $xeroVariance > 0 ? '#d9534f' : '#f0ad4e' ?>;">
                            <?= $xeroVariance > 0 ? '+' : '' ?>£<?= number_format($xeroVariance, 2) ?>
                            <span style="font-size: 0.9em;">(<?= number_format(abs($variancePercent), 1) ?>%)</span>
                        </strong>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Xero Invoice Number:</td>
                <td><?= htmlspecialchars($invoice['xero_invoice_number']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Reconciled Date:</td>
                <td>
                    <?= date('d/m/Y', strtotime($invoice['reconciled_date'])) ?>
                    by <?= htmlspecialchars($invoice['reconciled_by']) ?>
                </td>
            </tr>
        </table>

        <?php if (abs($xeroVariance) > 0.01): ?>
            <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <strong>⚠ Variance Detected:</strong><br>
                <?php if ($xeroVariance > 0): ?>
                    Your system shows <strong>£<?= number_format($xeroVariance, 2) ?> MORE</strong> than Xero.
                    This means the Xero invoice is <strong>under-charged</strong> by this amount.
                <?php else: ?>
                    Your system shows <strong>£<?= number_format(abs($xeroVariance), 2) ?> LESS</strong> than Xero.
                    This means the Xero invoice is <strong>over-charged</strong> by this amount.
                <?php endif; ?>
                <br><br>
                <strong>Action Required:</strong> Please investigate and correct the discrepancy in Xero or your system.
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Individual Camera Allocations -->
    <?php if (!empty($individualCameras)): ?>
    <div class="card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0;">Allocated Cameras</h2>
            <a href="?page=invoices&action=allocate&id=<?= $invoiceId ?>" class="btn btn-primary">
                Manage Allocations
            </a>
        </div>

        <?php
        // Group cameras by store and determine pricing tier (first vs additional)
        $storeBreakdown = [];
        $totalAllocated = 0;

        foreach ($individualCameras as $camera) {
            $storeId = $camera['store_id'];

            if (!isset($storeBreakdown[$storeId])) {
                $storeBreakdown[$storeId] = [
                    'store_name' => $camera['store_name'],
                    'store_code' => $camera['store_code'],
                    'cameras' => []
                ];
            }

            $storeBreakdown[$storeId]['cameras'][] = $camera;
            $totalAllocated += $camera['price_charged'];
        }

        // For each store, determine which camera is "first" (highest price) and which are "additional"
        foreach ($storeBreakdown as &$store) {
            // Sort cameras by price descending to identify first camera
            usort($store['cameras'], function($a, $b) {
                return $b['price_charged'] <=> $a['price_charged'];
            });

            // Mark the first camera (highest price) as "First Camera"
            // and the rest as "Additional Camera"
            foreach ($store['cameras'] as $index => &$camera) {
                $camera['pricing_tier'] = ($index === 0) ? 'First Camera' : 'Additional Camera';
            }
        }
        ?>

        <table>
            <thead>
                <tr>
                    <th>Store</th>
                    <th>Store ID</th>
                    <th>Pricing Tier</th>
                    <th>Installation Date</th>
                    <th>Price Charged</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($storeBreakdown as $store): ?>
                    <?php foreach ($store['cameras'] as $index => $camera): ?>
                    <tr <?= $index === 0 ? 'style="border-top: 2px solid #007bff;"' : '' ?>>
                        <?php if ($index === 0): ?>
                        <td rowspan="<?= count($store['cameras']) ?>" style="vertical-align: top; font-weight: bold; background: #f8f9fa;">
                            <?= htmlspecialchars($store['store_name']) ?>
                        </td>
                        <td rowspan="<?= count($store['cameras']) ?>" style="vertical-align: top; background: #f8f9fa;">
                            <?= htmlspecialchars($store['store_code']) ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <span class="badge <?= $camera['pricing_tier'] === 'First Camera' ? 'badge-success' : 'badge-info' ?>" style="font-size: 0.9em;">
                                <?= $camera['pricing_tier'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($camera['installation_date']): ?>
                                <?= date('d/m/Y', strtotime($camera['installation_date'])) ?>
                            <?php else: ?>
                                <em>N/A</em>
                            <?php endif; ?>
                        </td>
                        <td><strong>£<?= number_format($camera['price_charged'], 2) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="4">TOTAL (<?= count($individualCameras) ?> cameras)</td>
                    <td><strong>£<?= number_format($totalAllocated, 2) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #007bff; border-radius: 4px;">
            <strong>ℹ️ Pricing Explanation:</strong><br>
            Each store's <strong>first camera</strong> is charged at the full rate. Any <strong>additional cameras</strong> in the same store receive a discounted rate.
        </div>
    </div>
    <?php elseif (!empty($allocations)): ?>
    <!-- Legacy Camera Breakdown by Store -->
    <div class="card" style="margin-bottom: 20px;">
        <h2>Camera Breakdown by Store (Legacy)</h2>
        <table>
            <thead>
                <tr>
                    <th>Store</th>
                    <th>Store ID</th>
                    <th>Main Cameras</th>
                    <th>Additional Cameras</th>
                    <th>Main Rate</th>
                    <th>Additional Rate</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allocations as $allocation): ?>
                <tr>
                    <td><?= htmlspecialchars($allocation['store_name']) ?></td>
                    <td><?= htmlspecialchars($allocation['store_code']) ?></td>
                    <td><?= $allocation['main_cameras'] ?></td>
                    <td><?= $allocation['additional_cameras'] ?></td>
                    <td>£<?= number_format($allocation['main_camera_rate'], 2) ?></td>
                    <td>£<?= number_format($allocation['additional_camera_rate'], 2) ?></td>
                    <td><strong>£<?= number_format($allocation['subtotal'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="2">TOTAL</td>
                    <td><?= $totalMainCameras ?></td>
                    <td><?= $totalAdditionalCameras ?></td>
                    <td colspan="2"></td>
                    <td><strong>£<?= number_format($totalSubtotal, 2) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php else: ?>
    <div class="card" style="margin-bottom: 20px;">
        <h2>Camera Breakdown</h2>
        <p style="color: #999;">No camera allocations found for this invoice.</p>
        <p>
            <a href="?page=invoices&action=allocate&id=<?= $invoiceId ?>" class="btn btn-success">
                Allocate Cameras to This Invoice
            </a>
        </p>
    </div>
    <?php endif; ?>

    <!-- Status Change History -->
    <?php
    // Get status history
    require_once __DIR__ . '/../../app/Services/InvoiceStatusService.php';
    use App\Services\InvoiceStatusService;

    $statusService = new InvoiceStatusService();
    $statusHistory = $statusService->getStatusHistory($invoiceId);
    ?>

    <?php if (!empty($statusHistory)): ?>
    <div class="card" style="margin-bottom: 20px;">
        <h2>Status Change History</h2>
        <table>
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>From Status</th>
                    <th>To Status</th>
                    <th>Changed By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statusHistory as $history): ?>
                <tr>
                    <td><?= date('d/m/Y H:i:s', strtotime($history['changed_at'])) ?></td>
                    <td>
                        <?php if ($history['old_status']): ?>
                            <span class="badge badge-secondary"><?= ucfirst(str_replace('_', ' ', $history['old_status'])) ?></span>
                        <?php else: ?>
                            <em>New</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-info"><?= ucfirst(str_replace('_', ' ', $history['new_status'])) ?></span>
                    </td>
                    <td><?= htmlspecialchars($history['changed_by']) ?></td>
                    <td><?= htmlspecialchars($history['notes'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($invoice['notes']): ?>
    <!-- Notes -->
    <div class="card">
        <h2>Notes</h2>
        <p><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

