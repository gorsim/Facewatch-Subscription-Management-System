<?php
/**
 * Review and Adjust Camera Pricing
 * Intermediate step between generator and invoice creation
 * Allows manual adjustment of individual camera prices
 */

use App\Database;
use App\Services\PricingService;

$pageTitle = 'Review Invoice Pricing';
$page = 'invoices';

$db = Database::getInstance();
$pricingService = new PricingService();

// Get form data from POST
$cameraIds = $_POST['cameras'] ?? [];
$invoiceDate = $_POST['invoice_date'] ?? null;
$overrideAmount = !empty($_POST['override_amount']) ? floatval($_POST['override_amount']) : null;

// Validate input
if (empty($cameraIds)) {
    $_SESSION['error'] = 'No cameras selected';
    header('Location: ?page=invoices&action=generator');
    exit;
}

if (!$invoiceDate) {
    $_SESSION['error'] = 'Invoice date is required';
    header('Location: ?page=invoices&action=generator');
    exit;
}

// Get camera details
$placeholders = implode(',', array_fill(0, count($cameraIds), '?'));
$cameras = $db->fetchAll(
    "SELECT ci.*, s.store_name, s.store_code, le.id as legal_entity_id, le.legal_entity_name
     FROM camera_installations ci
     JOIN stores s ON ci.store_id = s.id
     JOIN legal_entities le ON s.legal_entity_id = le.id
     WHERE ci.id IN ($placeholders)",
    $cameraIds
);

// Verify all cameras belong to same legal entity
$legalEntityId = $cameras[0]['legal_entity_id'];
foreach ($cameras as $camera) {
    if ($camera['legal_entity_id'] != $legalEntityId) {
        $_SESSION['error'] = 'All cameras must belong to the same legal entity';
        header('Location: ?page=invoices&action=generator');
        exit;
    }
}

// Get legal entity details
$legalEntity = $db->fetchOne(
    "SELECT * FROM legal_entities WHERE id = :id",
    ['id' => $legalEntityId]
);

// Group cameras by store
$camerasByStore = [];
foreach ($cameras as $camera) {
    $storeId = $camera['store_id'];
    if (!isset($camerasByStore[$storeId])) {
        $camerasByStore[$storeId] = [];
    }
    $camerasByStore[$storeId][] = $camera;
}

// Count first and additional cameras
$totalCameras = count($cameras);
$firstCameras = count($camerasByStore);
$additionalCameras = $totalCameras - $firstCameras;

// Get pricing information
$pricing = $pricingService->getPricingForEntity($legalEntityId, $totalCameras, $invoiceDate);

// Calculate standard pricing for each camera
$storeFirstCameraAssigned = [];
$cameraAllocations = [];
$calculatedTotal = 0;

foreach ($cameras as $camera) {
    $storeId = $camera['store_id'];
    $isFirstCameraInStore = !isset($storeFirstCameraAssigned[$storeId]);

    if ($pricing['pricing_type'] === 'first_plus_additional') {
        if ($isFirstCameraInStore) {
            $priceForThisCamera = $pricing['first_camera_rate'];
            $pricingTier = 'First Camera';
            $storeFirstCameraAssigned[$storeId] = true;
        } else {
            $priceForThisCamera = $pricing['additional_camera_rate'];
            $pricingTier = 'Additional Camera';
        }
    } else {
        $priceForThisCamera = $pricing['rate_to_use'];
        $pricingTier = $pricing['tier_name'] ?? 'Standard';
    }

    $calculatedTotal += $priceForThisCamera;
    $cameraAllocations[] = [
        'camera' => $camera,
        'price' => $priceForThisCamera,
        'tier' => $pricingTier
    ];
}

// Calculate variance if override amount provided
$targetAmount = $overrideAmount ?? $calculatedTotal;
$variance = $targetAmount - $calculatedTotal;

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div class="card">
        <h2>📝 Review Invoice Pricing</h2>
        <p style="color: #666;">Review and adjust individual camera prices before creating the invoice.</p>
    </div>

    <div class="card">
        <h3>Invoice Details</h3>
        <table style="width: auto;">
            <tr>
                <th style="text-align: right; padding-right: 15px;">Legal Entity:</th>
                <td><strong><?= htmlspecialchars($legalEntity['legal_entity_name']) ?></strong></td>
            </tr>
            <tr>
                <th style="text-align: right; padding-right: 15px;">Invoice Date:</th>
                <td><?= date('d/m/Y', strtotime($invoiceDate)) ?></td>
            </tr>
            <tr>
                <th style="text-align: right; padding-right: 15px;">Total Cameras:</th>
                <td><?= $totalCameras ?> (<?= $firstCameras ?> first, <?= $additionalCameras ?> additional)</td>
            </tr>
        </table>
    </div>

    <!-- Pricing Summary -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Pricing Summary</h3>
            <div id="varianceDisplay" style="padding: 10px 20px; border-radius: 4px; font-weight: bold; <?= abs($variance) > 0.01 ? 'background: #fff3cd; color: #856404;' : 'background: #d4edda; color: #155724;' ?>">
                <?php if (abs($variance) > 0.01): ?>
                    ⚠️ Variance: £<?= number_format(abs($variance), 2) ?> <?= $variance > 0 ? 'over' : 'under' ?>
                <?php else: ?>
                    ✅ Balanced
                <?php endif; ?>
            </div>
        </div>

        <table style="width: auto; margin-bottom: 20px;">
            <tr>
                <th style="text-align: right; padding-right: 15px;">Calculated Total:</th>
                <td><strong>£<?= number_format($calculatedTotal, 2) ?></strong></td>
            </tr>
            <?php if ($overrideAmount !== null): ?>
            <tr>
                <th style="text-align: right; padding-right: 15px;">Override Amount:</th>
                <td><strong style="color: #e74c3c;">£<?= number_format($overrideAmount, 2) ?></strong></td>
            </tr>
            <tr>
                <th style="text-align: right; padding-right: 15px;">Variance:</th>
                <td><strong style="color: <?= $variance > 0 ? '#e74c3c' : '#27ae60' ?>;">£<?= number_format($variance, 2) ?></strong></td>
            </tr>
            <?php endif; ?>
            <tr style="border-top: 2px solid #333;">
                <th style="text-align: right; padding-right: 15px;">Current Total:</th>
                <td><strong id="currentTotal" style="font-size: 1.2em; color: #2c3e50;">£<?= number_format($calculatedTotal, 2) ?></strong></td>
            </tr>
            <tr>
                <th style="text-align: right; padding-right: 15px;">Target Amount:</th>
                <td><strong style="font-size: 1.2em; color: #3498db;">£<?= number_format($targetAmount, 2) ?></strong></td>
            </tr>
        </table>

        <?php if (abs($variance) > 0.01): ?>
        <div style="background: #fff3cd; padding: 15px; border-radius: 4px; border-left: 4px solid #ffc107;">
            <p style="margin: 0; font-weight: bold;">💡 Tip:</p>
            <p style="margin: 5px 0 0 0;">Adjust the camera prices below to match the target amount. The variance will update automatically.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Camera Pricing Table -->
    <form method="POST" action="?page=invoices&action=create_from_review" id="reviewForm">
        <input type="hidden" name="invoice_date" value="<?= htmlspecialchars($invoiceDate) ?>">
        <input type="hidden" name="legal_entity_id" value="<?= $legalEntityId ?>">
        <input type="hidden" name="target_amount" value="<?= $targetAmount ?>">

        <div class="card">
            <h3>Camera Pricing</h3>
            <p style="color: #666; margin-bottom: 20px;">Edit the price for any camera to adjust the total. Prices are grouped by store.</p>

            <table>
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Camera</th>
                        <th>Type</th>
                        <th>Pricing Tier</th>
                        <th style="text-align: right;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $currentStore = null;
                    foreach ($cameraAllocations as $index => $allocation):
                        $camera = $allocation['camera'];
                        $showStore = $currentStore !== $camera['store_id'];
                        $currentStore = $camera['store_id'];
                    ?>
                    <tr>
                        <td><?= $showStore ? htmlspecialchars($camera['store_name']) : '' ?></td>
                        <td><?= htmlspecialchars($camera['camera_name'] ?? 'Camera ' . $camera['id']) ?></td>
                        <td><span class="badge badge-info"><?= ucfirst($camera['camera_type']) ?></span></td>
                        <td><span class="badge <?= $allocation['tier'] === 'First Camera' ? 'badge-success' : 'badge-secondary' ?>"><?= $allocation['tier'] ?></span></td>
                        <td style="text-align: right;">
                            <input type="hidden" name="camera_ids[]" value="<?= $camera['id'] ?>">
                            <input type="hidden" name="pricing_tiers[]" value="<?= htmlspecialchars($allocation['tier']) ?>">
                            £<input
                                type="number"
                                name="camera_prices[]"
                                class="camera-price-input"
                                value="<?= number_format($allocation['price'], 2, '.', '') ?>"
                                step="0.01"
                                min="0"
                                style="width: 100px; text-align: right; padding: 5px; border: 1px solid #ddd; border-radius: 3px;"
                                data-original="<?= $allocation['price'] ?>"
                            >
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid #333; font-weight: bold;">
                        <td colspan="4" style="text-align: right; padding-right: 15px;">Total:</td>
                        <td style="text-align: right;">
                            <span id="tableTotal" style="font-size: 1.1em;">£<?= number_format($calculatedTotal, 2) ?></span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <a href="?page=invoices&action=generator" class="btn">← Back to Generator</a>
                <button type="submit" class="btn btn-success" id="createButton" style="font-size: 1.1em; padding: 12px 30px;">
                    📄 Create Invoice
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// Calculate total from all price inputs
function calculateTotal() {
    const inputs = document.querySelectorAll('.camera-price-input');
    let total = 0;

    inputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        total += value;
    });

    return total;
}

// Update displays
function updateDisplays() {
    const total = calculateTotal();
    const targetAmount = <?= $targetAmount ?>;
    const variance = total - targetAmount;

    // Update total display
    document.getElementById('tableTotal').textContent = '£' + total.toFixed(2);
    document.getElementById('currentTotal').textContent = '£' + total.toFixed(2);

    // Update variance display
    const varianceDisplay = document.getElementById('varianceDisplay');
    if (Math.abs(variance) < 0.01) {
        varianceDisplay.innerHTML = '✅ Balanced';
        varianceDisplay.style.background = '#d4edda';
        varianceDisplay.style.color = '#155724';
    } else {
        const direction = variance > 0 ? 'over' : 'under';
        varianceDisplay.innerHTML = '⚠️ Variance: £' + Math.abs(variance).toFixed(2) + ' ' + direction;
        varianceDisplay.style.background = '#fff3cd';
        varianceDisplay.style.color = '#856404';
    }
}

// Add event listeners to all price inputs
document.querySelectorAll('.camera-price-input').forEach(input => {
    input.addEventListener('input', updateDisplays);
    input.addEventListener('change', updateDisplays);
});

// Form validation
document.getElementById('reviewForm').addEventListener('submit', function(e) {
    const total = calculateTotal();
    const targetAmount = <?= $targetAmount ?>;
    const variance = Math.abs(total - targetAmount);

    if (variance > 0.01) {
        const confirmed = confirm(
            'Warning: The total (£' + total.toFixed(2) + ') does not match the target amount (£' + targetAmount.toFixed(2) + ').\n\n' +
            'Variance: £' + variance.toFixed(2) + '\n\n' +
            'Do you want to create the invoice anyway?'
        );

        if (!confirmed) {
            e.preventDefault();
            return false;
        }
    }
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
