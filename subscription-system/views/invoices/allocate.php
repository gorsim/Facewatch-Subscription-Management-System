<?php
/**
 * Invoice Camera Allocation Screen
 * Allocate cameras to an invoice and calculate expected amount
 */

use App\Database;
use App\Services\CameraAllocationService;
use App\Services\InvoiceReconciliationService;
use App\Services\PricingService;

$pageTitle = 'Allocate Cameras to Invoice';
$page = 'invoices';

$invoiceId = $_GET['id'] ?? null;
$db = Database::getInstance();

if (!$invoiceId) {
    header('Location: ?page=invoices');
    exit;
}

// Get invoice with legal entity details
try {
    $invoice = $db->fetchOne(
        "SELECT i.*, le.legal_entity_name, le.xero_company_name
         FROM invoices i
         JOIN legal_entities le ON i.legal_entity_id = le.id
         WHERE i.id = :id",
        ['id' => $invoiceId]
    );
} catch (Exception $e) {
    error_log("Error fetching invoice: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading invoice: ' . $e->getMessage();
    header('Location: ?page=invoices');
    exit;
}

if (!$invoice) {
    $_SESSION['error'] = 'Invoice not found';
    header('Location: ?page=invoices');
    exit;
}

// Handle removal of allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_allocation'])) {
    error_log("Remove allocation request received");
    $allocationId = $_POST['allocation_id'] ?? null;
    error_log("Allocation ID: " . ($allocationId ?? 'NULL'));

    if ($allocationId) {
        $allocationService = new CameraAllocationService();
        $reconciliationService = new InvoiceReconciliationService();

        try {
            error_log("Attempting to remove allocation ID: $allocationId");
            $result = $allocationService->removeAllocation($allocationId);
            error_log("Remove allocation result: " . ($result ? 'SUCCESS' : 'FAILED'));

            $reconciliationService->reconcileInvoice($invoiceId);

            $_SESSION['success'] = 'Camera allocation removed successfully!';
            header('Location: ?page=invoices&action=allocate&id=' . $invoiceId);
            exit;
        } catch (Exception $e) {
            error_log("Remove Allocation Error: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to remove allocation: ' . $e->getMessage();
        }
    } else {
        error_log("No allocation ID provided");
        $_SESSION['error'] = 'No allocation ID provided';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['remove_allocation'])) {
    $selectedCameras = $_POST['cameras'] ?? [];

    $allocationService = new CameraAllocationService();
    $reconciliationService = new InvoiceReconciliationService();

    try {
        // Get TOTAL active cameras for this legal entity to determine pricing tier
        $asOfDate = $invoice['invoice_date'];

        $totalActiveCameras = $db->fetchOne(
            "SELECT COUNT(*) as total
             FROM camera_installations ci
             JOIN stores s ON ci.store_id = s.id
             WHERE s.legal_entity_id = :legal_entity_id
             AND ci.installation_date <= :as_of_date
             AND (ci.removal_date IS NULL OR ci.removal_date > :as_of_date2)",
            [
                'legal_entity_id' => $invoice['legal_entity_id'],
                'as_of_date' => $asOfDate,
                'as_of_date2' => $asOfDate
            ]
        );

        $totalCameraCount = intval($totalActiveCameras['total'] ?? 0);

        // Get pricing based on TOTAL camera count (for tier)
        $pricingService = new PricingService();
        $pricing = $pricingService->getPricingForEntity(
            $invoice['legal_entity_id'],
            $totalCameraCount,
            $asOfDate
        );

        $correctRate = $pricing['rate_to_use'];
        error_log("Allocate submission - Using rate: £$correctRate for $totalCameraCount total cameras");

        // Get currently allocated camera IDs
        $currentAllocations = $allocationService->getIndividualCameraAllocations($invoiceId);
        $currentCameraIds = array_column($currentAllocations, 'camera_installation_id');

        // Only allocate cameras that aren't already allocated
        $newAllocations = 0;
        $totalAmount = 0;

        foreach ($selectedCameras as $cameraId) {
            // Skip if already allocated
            if (in_array($cameraId, $currentCameraIds)) {
                continue;
            }

            $camera = $db->fetchOne(
                "SELECT ci.*, s.legal_entity_id
                 FROM camera_installations ci
                 JOIN stores s ON ci.store_id = s.id
                 WHERE ci.id = :id",
                ['id' => $cameraId]
            );

            if ($camera) {
                // Use the correct rate based on total camera count
                $rate = $correctRate;

                // Insert allocation using the correct column names
                $db->insert('invoice_camera_allocations', [
                    'invoice_id' => $invoiceId,
                    'camera_installation_id' => $cameraId,
                    'store_id' => $camera['store_id'],
                    'legal_entity_id' => $camera['legal_entity_id'],
                    'price_charged' => $rate,
                    'pricing_tier' => $pricing['tier_name'],
                    'allocated_date' => date('Y-m-d H:i:s')
                ]);

                $totalAmount += $rate;
                $newAllocations++;
            }
        }

        // Reconcile invoice
        $reconResult = $reconciliationService->reconcileInvoice($invoiceId);

        if ($newAllocations > 0) {
            $_SESSION['success'] = "Allocated " . $newAllocations . " new camera(s) successfully! Added: £" . number_format($totalAmount, 2);
        } else {
            $_SESSION['info'] = "No new cameras were allocated (selected cameras are already allocated).";
        }
        header('Location: ?page=invoices&action=view&id=' . $invoiceId);
        exit;
    } catch (Exception $e) {
        error_log("Allocation Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['error'] = 'Failed to allocate cameras: ' . $e->getMessage();
    }
}

// Get currently allocated cameras for this invoice
$allocationService = new CameraAllocationService();
$currentlyAllocated = $allocationService->getIndividualCameraAllocations($invoiceId);

// Get date range from query params or default to invoice date
$startDate = $_GET['start_date'] ?? $invoice['invoice_date'];
$endDate = $_GET['end_date'] ?? $invoice['invoice_date'];

// Get TOTAL active cameras for this legal entity to determine pricing tier
$asOfDate = $invoice['invoice_date'];

$totalActiveCameras = $db->fetchOne(
    "SELECT COUNT(*) as total
     FROM camera_installations ci
     JOIN stores s ON ci.store_id = s.id
     WHERE s.legal_entity_id = :legal_entity_id
     AND ci.installation_date <= :as_of_date
     AND (ci.removal_date IS NULL OR ci.removal_date > :as_of_date2)",
    [
        'legal_entity_id' => $invoice['legal_entity_id'],
        'as_of_date' => $asOfDate,
        'as_of_date2' => $asOfDate
    ]
);

$totalCameraCount = intval($totalActiveCameras['total'] ?? 0);
error_log("Allocate page - Total active cameras for entity {$invoice['legal_entity_id']}: $totalCameraCount");

// Get pricing based on TOTAL camera count (for tier)
$pricingService = new PricingService();
$pricing = $pricingService->getPricingForEntity(
    $invoice['legal_entity_id'],
    $totalCameraCount,
    $asOfDate
);

error_log("Allocate page - Pricing: " . print_r($pricing, true));

// Get unallocated cameras for this legal entity within date range
try {
    $cameras = $db->fetchAll(
        "SELECT ci.*, s.store_name, s.store_id
         FROM camera_installations ci
         JOIN stores s ON ci.store_id = s.id
         WHERE s.legal_entity_id = :legal_entity_id
         AND ci.installation_date >= :start_date
         AND ci.installation_date <= :end_date
         AND (ci.removal_date IS NULL OR ci.removal_date > :start_date2)
         AND ci.id NOT IN (
             SELECT camera_installation_id
             FROM invoice_camera_allocations
             WHERE camera_installation_id IS NOT NULL
         )
         ORDER BY s.store_name, ci.installation_date, ci.camera_type",
        [
            'legal_entity_id' => $invoice['legal_entity_id'],
            'start_date' => $startDate,
            'start_date2' => $startDate,
            'end_date' => $endDate
        ]
    );

    // Apply the correct pricing to each camera based on the pricing tier
    foreach ($cameras as &$camera) {
        $camera['rate'] = $pricing['rate_to_use'];
    }
    unset($camera); // Break reference

} catch (Exception $e) {
    error_log("Error fetching cameras: " . $e->getMessage());
    error_log("Parameters: legal_entity_id=" . $invoice['legal_entity_id'] . ", start_date=" . $startDate . ", end_date=" . $endDate);
    $_SESSION['error'] = 'Error loading cameras: ' . $e->getMessage();
    $cameras = [];
}

// Get pricing rates for display
$rates = $db->fetchOne(
    "SELECT main_camera_rate, additional_camera_rate, payment_frequency
     FROM legal_entities
     WHERE id = :id",
    ['id' => $invoice['legal_entity_id']]
);

// Calculate total of currently allocated cameras
$currentlyAllocatedTotal = 0;
foreach ($currentlyAllocated as $camera) {
    $currentlyAllocatedTotal += $camera['price_charged'] ?? 0;
}

// Calculate expected total if all available cameras selected
$expectedTotal = 0;
foreach ($cameras as $camera) {
    $expectedTotal += $camera['rate'];
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Allocate Cameras to Invoice</h1>
        <div style="display: flex; gap: 10px;">
            <a href="?page=invoices&action=view&id=<?= $invoiceId ?>" class="btn">← Back to Invoice</a>
        </div>
    </div>

    <!-- Invoice Summary -->
    <div class="card" style="margin-bottom: 20px;">
        <h2>Invoice Summary</h2>
        <table style="width: 100%;">
            <tr>
                <td style="width: 200px; font-weight: bold;">Invoice Number:</td>
                <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Legal Entity:</td>
                <td><?= htmlspecialchars($invoice['legal_entity_name']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Invoice Date:</td>
                <td><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Actual Invoice Amount:</td>
                <td style="font-size: 1.2em;"><strong>£<?= number_format($invoice['invoice_amount'], 2) ?></strong></td>
            </tr>
        </table>
    </div>

    <!-- Currently Allocated Cameras -->
    <?php if (!empty($currentlyAllocated)): ?>
    <div class="card" style="margin-bottom: 20px;">
        <h2>✓ Currently Allocated Cameras</h2>
        <p style="color: #666; margin-bottom: 15px;">
            These cameras are currently allocated to this invoice. You can remove them if needed.
        </p>
        <table>
            <thead>
                <tr>
                    <th>Store</th>
                    <th>Store ID</th>
                    <th>Camera Type</th>
                    <th>Installation Date</th>
                    <th>Rate</th>
                    <th>Subtotal</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $allocatedTotal = 0;
                foreach ($currentlyAllocated as $camera):
                    $allocatedTotal += $camera['price_charged'] ?? 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars($camera['store_name']) ?></td>
                    <td><?= htmlspecialchars($camera['store_code']) ?></td>
                    <td>
                        <?php if ($camera['camera_type']): ?>
                            <span class="badge <?= $camera['camera_type'] === 'main' ? 'badge-primary' : 'badge-secondary' ?>">
                                <?= ucfirst($camera['camera_type']) ?>
                            </span>
                        <?php else: ?>
                            <em>N/A</em>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y', strtotime($camera['installation_date'])) ?></td>
                    <td>£<?= number_format($camera['price_charged'] ?? 0, 2) ?></td>
                    <td><strong>£<?= number_format($camera['price_charged'] ?? 0, 2) ?></strong></td>
                    <td>
                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to remove this camera allocation?');">
                            <input type="hidden" name="remove_allocation" value="1">
                            <input type="hidden" name="allocation_id" value="<?= $camera['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="5">TOTAL (<?= count($currentlyAllocated) ?> cameras)</td>
                    <td><strong>£<?= number_format($allocatedTotal, 2) ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <!-- Date Range Filter -->
    <div class="card" style="margin-bottom: 20px;">
        <h2>📅 Filter Cameras by Date Range</h2>
        <form method="GET" style="display: flex; gap: 15px; align-items: end;">
            <input type="hidden" name="page" value="invoices">
            <input type="hidden" name="action" value="allocate">
            <input type="hidden" name="id" value="<?= $invoiceId ?>">

            <div class="form-group" style="margin: 0;">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= $startDate ?>">
            </div>

            <div class="form-group" style="margin: 0;">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= $endDate ?>">
            </div>

            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="?page=invoices&action=allocate&id=<?= $invoiceId ?>" class="btn">Reset</a>
        </form>
        <small style="color: #666; margin-top: 10px; display: block;">
            Showing cameras installed between <?= date('d/m/Y', strtotime($startDate)) ?> and <?= date('d/m/Y', strtotime($endDate)) ?> that haven't been allocated to any invoice yet.
        </small>
    </div>

    <!-- Camera Selection Form -->
    <form method="POST" id="allocationForm">
        <div class="card" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2>📹 Select Cameras to Allocate</h2>
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="selectAll()" class="btn btn-sm">✓ Select All</button>
                    <button type="button" onclick="deselectAll()" class="btn btn-sm">✗ Deselect All</button>
                </div>
            </div>

            <?php if (empty($cameras)): ?>
                <p style="color: #999; text-align: center; padding: 40px;">
                    No unallocated cameras found for this date range.<br>
                    Try adjusting the date range or check if cameras have already been allocated to other invoices.
                </p>
            <?php else: ?>
                <table id="cameraTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                            </th>
                            <th>Store</th>
                            <th>Store ID</th>
                            <th>Installation Date</th>
                            <th>Camera Type</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cameras as $camera): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="cameras[]" value="<?= $camera['id'] ?>"
                                       class="camera-checkbox" data-rate="<?= $camera['rate'] ?>"
                                       onchange="updateTotal()">
                            </td>
                            <td><?= htmlspecialchars($camera['store_name']) ?></td>
                            <td><?= htmlspecialchars($camera['store_id']) ?></td>
                            <td><?= date('d/m/Y', strtotime($camera['installation_date'])) ?></td>
                            <td>
                                <span class="badge <?= $camera['camera_type'] === 'main' ? 'badge-primary' : 'badge-secondary' ?>">
                                    <?= ucfirst($camera['camera_type']) ?>
                                </span>
                            </td>
                            <td>£<?= number_format($camera['rate'], 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Summary and Submit -->
        <?php if (!empty($cameras)): ?>
        <div class="card">
            <h2>📊 Allocation Summary</h2>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 200px; font-weight: bold;">Selected Cameras:</td>
                    <td><strong id="selectedCount">0</strong> cameras</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Expected Amount:</td>
                    <td style="font-size: 1.2em; color: #5cb85c;">
                        <strong>£<span id="expectedAmount">0</span></strong>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Actual Invoice Amount:</td>
                    <td style="font-size: 1.2em;">
                        <strong>£<?= number_format($invoice['invoice_amount'], 2) ?></strong>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Variance:</td>
                    <td style="font-size: 1.2em;">
                        <strong id="variance" style="color: #999;">£0.00</strong>
                    </td>
                </tr>
            </table>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                    ✓ Allocate Selected Cameras
                </button>
                <a href="?page=invoices&action=view&id=<?= $invoiceId ?>" class="btn">Cancel</a>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
const invoiceAmount = <?= $invoice['invoice_amount'] ?>;
const currentlyAllocatedTotal = <?= $currentlyAllocatedTotal ?>;
const currentlyAllocatedCount = <?= count($currentlyAllocated) ?>;

function updateTotal() {
    const checkboxes = document.querySelectorAll('.camera-checkbox:checked');
    let newlySelectedTotal = 0;

    checkboxes.forEach(cb => {
        newlySelectedTotal += parseFloat(cb.dataset.rate);
    });

    // Combine currently allocated cameras with newly selected ones
    const total = currentlyAllocatedTotal + newlySelectedTotal;
    const totalCount = currentlyAllocatedCount + checkboxes.length;

    // Update display (only if elements exist - they're hidden when no cameras available)
    const selectedCountEl = document.getElementById('selectedCount');
    const expectedAmountEl = document.getElementById('expectedAmount');
    const varianceEl = document.getElementById('variance');
    const submitBtn = document.getElementById('submitBtn');

    if (selectedCountEl) {
        selectedCountEl.textContent = totalCount;
    }

    if (expectedAmountEl) {
        expectedAmountEl.textContent = total.toLocaleString('en-GB', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    // Calculate variance (increase in invoice amount)
    if (varianceEl) {
        const variance = total - invoiceAmount;
        varianceEl.textContent = (variance >= 0 ? '+' : '') + '£' + Math.abs(variance).toLocaleString('en-GB', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // Color code variance
        if (Math.abs(variance) <= 1) {
            varianceEl.style.color = '#5cb85c'; // Green - no change
        } else if (variance > 0) {
            varianceEl.style.color = '#f0ad4e'; // Orange - increase
        } else {
            varianceEl.style.color = '#d9534f'; // Red - decrease
        }
    }

    // Enable/disable submit button
    if (submitBtn) {
        submitBtn.disabled = checkboxes.length === 0;
    }
}

function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.camera-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateTotal();
}

function selectAll() {
    document.getElementById('selectAllCheckbox').checked = true;
    toggleAll(document.getElementById('selectAllCheckbox'));
}

function deselectAll() {
    document.getElementById('selectAllCheckbox').checked = false;
    toggleAll(document.getElementById('selectAllCheckbox'));
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateTotal();
});
</script>

<style>
.btn-sm {
    padding: 5px 10px;
    font-size: 0.85em;
}

.btn-danger {
    background-color: #d9534f;
    color: white;
}

.btn-danger:hover {
    background-color: #c9302c;
}
</style>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

