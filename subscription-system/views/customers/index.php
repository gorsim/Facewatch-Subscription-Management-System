<?php
/**
 * Legal Entities / Customers Page
 * UPDATED: Now shows legal entities with store counts
 */

use App\Models\LegalEntity;
use App\Services\PricingService;

$pageTitle = 'Legal Entities';
$page = 'subscribers';

$legalEntityModel = new LegalEntity();
$pricingService = new PricingService();
$search = $_GET['search'] ?? '';

if ($search) {
    $legalEntities = $legalEntityModel->search($search);
} else {
    $legalEntities = $legalEntityModel->getAllWithContracts();
}

// Calculate totals
use App\Database;
$db = Database::getInstance();

$totalLegalEntities = count($legalEntities);

$totalStores = $db->fetchOne("SELECT COUNT(*) as count FROM stores");
$totalStoresCount = $totalStores['count'];

$totalCameras = $db->fetchOne(
    "SELECT COUNT(*) as count
     FROM camera_installations
     WHERE removal_date IS NULL"
);
$totalCamerasCount = $totalCameras['count'];

require __DIR__ . '/../layouts/header.php';
?>

<!-- Summary Statistics -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px;">
    <div class="card" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <h3 style="margin: 0 0 10px 0; font-size: 16px; opacity: 0.9;">Legal Entities</h3>
        <p style="margin: 0; font-size: 48px; font-weight: bold;"><?= $totalLegalEntities ?></p>
    </div>

    <div class="card" style="text-align: center; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
        <h3 style="margin: 0 0 10px 0; font-size: 16px; opacity: 0.9;">Total Stores</h3>
        <p style="margin: 0; font-size: 48px; font-weight: bold;"><?= $totalStoresCount ?></p>
    </div>

    <div class="card" style="text-align: center; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
        <h3 style="margin: 0 0 10px 0; font-size: 16px; opacity: 0.9;">Total Cameras</h3>
        <p style="margin: 0; font-size: 48px; font-weight: bold;"><?= $totalCamerasCount ?></p>
    </div>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Legal Entities (<?= count($legalEntities) ?>)</h2>
        <a href="?page=subscribers&action=new" class="btn btn-success">+ Add New Legal Entity</a>
    </div>

    <form method="GET" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="subscribers">
        <div style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search by name, ID, or lookup code..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <button type="submit" class="btn">Search</button>
            <?php if ($search): ?>
                <a href="?page=subscribers" class="btn">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($legalEntities)): ?>
        <p>No legal entities found. <?php if ($search): ?>Try a different search term or <?php endif; ?><a href="?page=import&type=xero">import from Xero</a>.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Legal Entity ID</th>
                    <th>Legal Entity Name</th>
                    <th>Xero Company Name</th>
                    <th>Stores</th>
                    <th>Total Cameras</th>
                    <th>Payment Frequency</th>
                    <th>Rate Per Camera</th>
                    <th>Pricing Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($legalEntities as $entity): ?>
                <?php
                    // Calculate current pricing using PricingService
                    $currentPricing = null;
                    $cameraCount = (int)($entity['total_cameras'] ?? 0);

                    try {
                        if ($cameraCount > 0) {
                            $currentPricing = $pricingService->getPricingForEntity(
                                $entity['id'],
                                $cameraCount,
                                date('Y-m-d')
                            );
                        }
                    } catch (Exception $e) {
                        // No pricing available yet
                        $currentPricing = null;
                    }
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($entity['legal_entity_id'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($entity['legal_entity_name']) ?></td>
                    <td><?= htmlspecialchars($entity['xero_company_name'] ?? '-') ?></td>
                    <td><?= number_format($entity['store_count'] ?? 0) ?></td>
                    <td><?= number_format($cameraCount) ?></td>
                    <td>
                        <?php if ($entity['payment_frequency']): ?>
                            <span class="badge badge-info"><?= ucfirst($entity['payment_frequency']) ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($currentPricing): ?>
                            £<?= number_format($currentPricing['rate_to_use'], 0) ?>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($entity['pricing_type'] === 'custom'): ?>
                            <span style="background: #fff3cd; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                🎯 Custom
                            </span>
                        <?php else: ?>
                            <span style="background: #d1ecf1; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                📊 Default
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=subscribers&action=view&id=<?= $entity['id'] ?>" class="btn" style="padding: 5px 10px; font-size: 12px;">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

