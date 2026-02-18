<?php
/**
 * Legal Entity Detail View
 */

use App\Models\LegalEntity;
use App\Models\Store;
use App\Database;
use App\Services\PricingService;

$pageTitle = 'Legal Entity Details';
$page = 'subscribers';

// Get legal entity ID from URL
$legalEntityId = $_GET['id'] ?? null;

if (!$legalEntityId) {
    header('Location: ?page=subscribers');
    exit;
}

$legalEntityModel = new LegalEntity();
$storeModel = new Store();
$db = Database::getInstance();

// Load legal entity with contract details
$legalEntity = $legalEntityModel->getWithContract($legalEntityId);

if (!$legalEntity) {
    $_SESSION['error'] = 'Legal Entity not found';
    header('Location: ?page=subscribers');
    exit;
}

// Get stores for this legal entity
$stores = $storeModel->getByLegalEntity($legalEntityId);

// Get current camera count and pricing tier
$cameraCount = $db->fetchOne("
    SELECT COUNT(*) as total
    FROM camera_installations ci
    JOIN stores s ON ci.store_id = s.id
    WHERE s.legal_entity_id = :id
    AND ci.removal_date IS NULL
", ['id' => $legalEntityId]);

$pricingService = new PricingService();
try {
    $currentPricing = $pricingService->getPricingForEntity(
        $legalEntityId,
        (int)($cameraCount['total'] ?? 0),
        date('Y-m-d')
    );
} catch (Exception $e) {
    // No pricing available yet (e.g., 0 cameras and no tier covers that)
    $currentPricing = null;
}

// Camera snapshots table was removed in Migration 023
// We now use camera_installations table for tracking cameras
$cameraHistory = [];

// Get invoices
$invoices = $db->fetchAll(
    "SELECT * FROM invoices
     WHERE legal_entity_id = :id
     ORDER BY invoice_date DESC",
    ['id' => $legalEntityId]
);

// Get latest camera count (aggregate from all stores)
$latestCameras = $db->fetchOne(
    "SELECT
        COUNT(CASE WHEN ci.camera_type = 'main' THEN 1 END) as cumulative_main_cameras,
        COUNT(CASE WHEN ci.camera_type = 'additional' THEN 1 END) as cumulative_additional_cameras,
        COUNT(*) as cumulative_total_cameras,
        MAX(ci.installation_date) as month_date
     FROM camera_installations ci
     JOIN stores s ON ci.store_id = s.id
     WHERE s.legal_entity_id = :id
     AND ci.removal_date IS NULL",
    ['id' => $legalEntityId]
);

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1><?= htmlspecialchars($legalEntity['legal_entity_name']) ?></h1>
        <div style="display: flex; gap: 10px;">
            <a href="?page=invoices&action=cluster&legal_entity_id=<?= $legalEntity['id'] ?>" class="btn btn-primary">📅 Invoice Timeline</a>
            <a href="?page=invoices&action=create&legal_entity_id=<?= $legalEntity['id'] ?>" class="btn btn-success">📄 Create Invoice</a>
            <a href="?page=subscribers&action=edit&id=<?= $legalEntity['id'] ?>" class="btn">✏️ Edit</a>
            <a href="?page=subscribers&action=delete&id=<?= $legalEntity['id'] ?>"
               class="btn btn-danger"
               onclick="return confirm('Are you sure you want to delete this legal entity? This will also delete all associated stores and data.')">🗑️ Delete</a>
            <a href="?page=subscribers" class="btn">← Back to Legal Entities</a>
        </div>
    </div>

    <!-- Legal Entity Details -->
    <div class="card" style="margin-bottom: 20px;">
        <h2>Legal Entity Information</h2>
        <table style="width: 100%;">
            <tr>
                <td style="width: 200px; font-weight: bold;">Legal Entity ID:</td>
                <td><?= htmlspecialchars($legalEntity['legal_entity_id'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Legal Entity Name:</td>
                <td><?= htmlspecialchars($legalEntity['legal_entity_name'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Xero Company Name:</td>
                <td><?= htmlspecialchars($legalEntity['xero_company_name'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Installation Date:</td>
                <td><?= $legalEntity['installation_date'] ? date('d/m/Y', strtotime($legalEntity['installation_date'])) : 'N/A' ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Termination Date:</td>
                <td><?= $legalEntity['termination_date'] ? date('d/m/Y', strtotime($legalEntity['termination_date'])) : 'Active' ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Number of Stores:</td>
                <td><strong><?= count($stores) ?></strong></td>
            </tr>
        </table>
    </div>

    <!-- Pricing Information -->
    <div class="card" style="margin-bottom: 20px;">
        <h2>Pricing Information</h2>
        <?php if ($legalEntity['payment_frequency'] && $currentPricing): ?>
        <table style="width: 100%;">
            <tr>
                <td style="width: 200px; font-weight: bold;">Payment Frequency:</td>
                <td><?= ucfirst($legalEntity['payment_frequency']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Pricing Type:</td>
                <td>
                    <?php if ($currentPricing['pricing_type'] === 'custom'): ?>
                        <span style="color: #0066cc; font-weight: bold;">Custom Pricing</span>
                        <a href="?page=admin&action=entity_pricing&id=<?= $legalEntityId ?>" style="margin-left: 10px; font-size: 0.9em;">Configure →</a>
                    <?php else: ?>
                        <span style="color: #28a745;">Default (Volume-Based)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Current Pricing Tier:</td>
                <td>
                    <strong><?= htmlspecialchars($currentPricing['tier_name'] ?? 'N/A') ?></strong>
                    <?php if ($currentPricing['pricing_type'] === 'default'): ?>
                        <span style="color: #666; font-size: 0.9em;">(automatically adjusts with camera count)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Rate Per Camera:</td>
                <td>
                    <strong>£<?= number_format($currentPricing['rate_to_use'] ?? 0, 2) ?></strong>
                    <span style="color: #666; font-size: 0.9em;">per <?= strtolower($legalEntity['payment_frequency']) ?></span>
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Total Active Cameras:</td>
                <td><strong><?= number_format($cameraCount['total'] ?? 0) ?></strong></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Estimated Cost:</td>
                <td>
                    <strong style="color: #0066cc; font-size: 1.1em;">
                        £<?= number_format(($cameraCount['total'] ?? 0) * ($currentPricing['rate_to_use'] ?? 0), 2) ?>
                    </strong>
                    <span style="color: #666; font-size: 0.9em;">per <?= strtolower($legalEntity['payment_frequency']) ?></span>
                </td>
            </tr>
        </table>

        <div style="margin-top: 15px; padding: 10px; background-color: #f8f9fa; border-left: 3px solid #0066cc;">
            <small style="color: #666;">
                <strong>Note:</strong>
                <?php if ($currentPricing['pricing_type'] === 'custom'): ?>
                    This entity uses custom pricing. <a href="?page=admin&action=entity_pricing&id=<?= $legalEntityId ?>">Configure custom pricing tiers →</a>
                <?php else: ?>
                    Pricing automatically adjusts based on total camera count across all stores.
                    View all tiers in the <a href="?page=admin&action=pricing_tiers_dashboard">Pricing Dashboard</a>.
                <?php endif; ?>
            </small>
        </div>
        <?php elseif ($legalEntity['payment_frequency'] && !$currentPricing): ?>
        <p style="color: #666;">
            <strong>No cameras installed yet.</strong><br>
            Pricing information will be displayed once cameras are added to stores.
            <?php if ($legalEntity['pricing_type'] === 'custom'): ?>
                <a href="?page=admin&action=entity_pricing&id=<?= $legalEntityId ?>">Configure custom pricing tiers →</a>
            <?php endif; ?>
        </p>
        <?php else: ?>
        <p style="color: #666;">No pricing information available. Payment frequency needs to be set.</p>
        <?php endif; ?>
    </div>

    <!-- Stores -->
    <?php if (!empty($stores)): ?>
    <div class="card" style="margin-bottom: 20px;">
        <h2>Stores (<?= count($stores) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>Store ID</th>
                    <th>Store Name</th>
                    <th>Installation Date</th>
                    <th>Active Cameras</th>
                    <th>Category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stores as $store): ?>
                <tr>
                    <td><?= htmlspecialchars($store['store_id']) ?></td>
                    <td><?= htmlspecialchars($store['store_name']) ?></td>
                    <td><?= $store['installation_date'] ? date('d/m/Y', strtotime($store['installation_date'])) : 'N/A' ?></td>
                    <td><?= number_format($store['active_cameras'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($store['category'] ?? 'N/A') ?></td>
                    <td>
                        <a href="?page=stores&action=view&id=<?= $store['id'] ?>" class="btn btn-sm">View Details</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="3" style="text-align: right;">TOTAL:</td>
                    <td><?= number_format(array_sum(array_column($stores, 'active_cameras'))) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <!-- Current Camera Count -->
    <?php if ($latestCameras && $latestCameras['cumulative_total_cameras'] > 0): ?>
    <div class="card" style="margin-bottom: 20px;">
        <h2>Current Camera Installation (All Stores)</h2>
        <p style="color: #666; font-size: 0.9em; margin-top: -10px;">Based on actual camera installation records</p>
        <table style="width: 100%;">
            <tr>
                <td style="width: 200px; font-weight: bold;">As of:</td>
                <td><?= $latestCameras['month_date'] ? date('d/m/Y', strtotime($latestCameras['month_date'])) : 'Current' ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Main Cameras:</td>
                <td><?= number_format($latestCameras['cumulative_main_cameras']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Additional Cameras:</td>
                <td><?= number_format($latestCameras['cumulative_additional_cameras']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Total Cameras:</td>
                <td><strong><?= number_format($latestCameras['cumulative_total_cameras']) ?></strong></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Camera History -->
    <?php if (!empty($cameraHistory)): ?>
    <div class="card" style="margin-bottom: 20px;">
        <h2>Camera Count Snapshots (Historical)</h2>
        <p style="color: #666; font-size: 0.9em; margin-top: -10px;">Based on imported monthly snapshot data - may differ from actual installations</p>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Main Cameras</th>
                    <th>Additional Cameras</th>
                    <th>Total Cameras</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cameraHistory as $record): ?>
                <tr>
                    <td><?= date('F Y', strtotime($record['month_date'])) ?></td>
                    <td><?= number_format($record['cumulative_main_cameras']) ?></td>
                    <td><?= number_format($record['cumulative_additional_cameras']) ?></td>
                    <td><?= number_format($record['cumulative_total_cameras']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

