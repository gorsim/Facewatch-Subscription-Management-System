<?php
/**
 * Legal Entities / Customers Page
 * UPDATED: Now shows legal entities with store counts
 */

use App\Models\LegalEntity;
use App\Services\PricingService;
use App\Models\Store;
use App\Models\CameraInstallation;
use App\Database;

$pageTitle = 'Legal Entities';
$page = 'subscribers';

$legalEntityModel = new LegalEntity();
$pricingService = new PricingService();
$storeModel = new Store();
$cameraModel = new CameraInstallation();
$db = Database::getInstance();

$search = $_GET['search'] ?? '';
$searchType = $_GET['search_type'] ?? 'all'; // all, entity, store, camera

// Initialize results
$legalEntities = [];
$storeResults = [];
$cameraResults = [];
$searchPerformed = false;

if ($search) {
    $searchPerformed = true;

    // Search based on type
    if ($searchType === 'all' || $searchType === 'entity') {
        $legalEntities = $legalEntityModel->search($search);
    }

    if ($searchType === 'all' || $searchType === 'store') {
        // Search stores by name, ID, or address
        $storeResults = $db->fetchAll("
            SELECT
                s.*,
                le.legal_entity_name,
                le.legal_entity_id,
                (SELECT COUNT(*)
                 FROM camera_installations ci
                 WHERE ci.store_id = s.id
                 AND ci.removal_date IS NULL) as active_cameras
            FROM stores s
            JOIN legal_entities le ON s.legal_entity_id = le.id
            WHERE s.store_name LIKE :term
            OR s.store_id LIKE :term
            OR s.store_code LIKE :term
            OR s.city LIKE :term
            OR s.postcode LIKE :term
            ORDER BY s.store_name
        ", ['term' => "%{$search}%"]);
    }

    if ($searchType === 'all' || $searchType === 'camera') {
        // Search cameras by SAFR code or camera name
        $cameraResults = $db->fetchAll("
            SELECT
                ci.*,
                s.store_name,
                s.store_id,
                le.legal_entity_name,
                le.legal_entity_id
            FROM camera_installations ci
            JOIN stores s ON ci.store_id = s.id
            JOIN legal_entities le ON s.legal_entity_id = le.id
            WHERE ci.safr_code LIKE :term
            OR ci.camera_name LIKE :term
            ORDER BY ci.installation_date DESC
            LIMIT 100
        ", ['term' => "%{$search}%"]);
    }
} else {
    // No search - show all legal entities
    $legalEntities = $legalEntityModel->getAllWithContracts();
}

// Calculate totals
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
        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
            <select name="search_type" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-width: 150px;">
                <option value="all" <?= $searchType === 'all' ? 'selected' : '' ?>>🔍 Search All</option>
                <option value="entity" <?= $searchType === 'entity' ? 'selected' : '' ?>>🏢 Legal Entities</option>
                <option value="store" <?= $searchType === 'store' ? 'selected' : '' ?>>🏪 Stores/Properties</option>
                <option value="camera" <?= $searchType === 'camera' ? 'selected' : '' ?>>📹 Cameras</option>
            </select>
            <input type="text" name="search" placeholder="Search by name, ID, SAFR code, address..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <button type="submit" class="btn">Search</button>
            <?php if ($search): ?>
                <a href="?page=subscribers" class="btn">Clear</a>
            <?php endif; ?>
        </div>
        <?php if ($searchType === 'store'): ?>
            <p style="margin: 0; font-size: 13px; color: #666;">💡 Tip: Search by store name, store ID, store code, city, or postcode</p>
        <?php elseif ($searchType === 'camera'): ?>
            <p style="margin: 0; font-size: 13px; color: #666;">💡 Tip: Search by SAFR code (e.g., "CA1A4D") or camera name</p>
        <?php endif; ?>
    </form>

    <!-- Store Search Results -->
    <?php if ($searchPerformed && !empty($storeResults)): ?>
        <div style="margin-bottom: 30px;">
            <h3 style="color: #f5576c; margin-bottom: 15px;">🏪 Store Results (<?= count($storeResults) ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Store ID</th>
                        <th>Store Name</th>
                        <th>Legal Entity</th>
                        <th>City</th>
                        <th>Postcode</th>
                        <th>Active Cameras</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($storeResults as $store): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($store['store_id']) ?></strong></td>
                        <td><?= htmlspecialchars($store['store_name']) ?></td>
                        <td>
                            <a href="?page=subscribers&action=view&id=<?= $store['legal_entity_id'] ?>">
                                <?= htmlspecialchars($store['legal_entity_name']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($store['city'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($store['postcode'] ?? '-') ?></td>
                        <td><?= number_format($store['active_cameras']) ?></td>
                        <td>
                            <a href="?page=stores&action=view&id=<?= $store['id'] ?>" class="btn" style="padding: 5px 10px; font-size: 12px;">View Store</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Camera Search Results -->
    <?php if ($searchPerformed && !empty($cameraResults)): ?>
        <div style="margin-bottom: 30px;">
            <h3 style="color: #4facfe; margin-bottom: 15px;">📹 Camera Results (<?= count($cameraResults) ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>SAFR Code</th>
                        <th>Camera Name</th>
                        <th>Store</th>
                        <th>Legal Entity</th>
                        <th>Installation Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cameraResults as $camera): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($camera['safr_code'] ?? '-') ?></strong></td>
                        <td><?= htmlspecialchars($camera['camera_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($camera['store_name']) ?></td>
                        <td>
                            <a href="?page=subscribers&action=view&id=<?= $camera['legal_entity_id'] ?>">
                                <?= htmlspecialchars($camera['legal_entity_name']) ?>
                            </a>
                        </td>
                        <td><?= $camera['installation_date'] ? date('d/m/Y', strtotime($camera['installation_date'])) : '-' ?></td>
                        <td>
                            <?php if ($camera['removal_date']): ?>
                                <span style="background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                    ❌ Removed <?= date('d/m/Y', strtotime($camera['removal_date'])) ?>
                                </span>
                            <?php else: ?>
                                <span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                    ✅ Active
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?page=subscribers&action=view&id=<?= $camera['legal_entity_id'] ?>" class="btn" style="padding: 5px 10px; font-size: 12px;">View Entity</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Legal Entity Results -->
    <?php if (empty($legalEntities) && ($searchType === 'all' || $searchType === 'entity')): ?>
        <p>No legal entities found. <?php if ($search): ?>Try a different search term or <?php endif; ?><a href="?page=import&type=xero">import from Xero</a>.</p>
    <?php elseif (!empty($legalEntities)): ?>
        <?php if ($searchPerformed && ($searchType === 'all' || $searchType === 'entity')): ?>
            <h3 style="color: #667eea; margin-bottom: 15px;">🏢 Legal Entity Results (<?= count($legalEntities) ?>)</h3>
        <?php endif; ?>
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

