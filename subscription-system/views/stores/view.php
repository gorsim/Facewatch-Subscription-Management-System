<?php
/**
 * View Store Details
 * Shows camera installations
 */

use App\Models\Store;
use App\Models\LegalEntity;
use App\Models\CameraInstallation;
use App\Database;

$pageTitle = 'Store Details';
$page = 'subscribers';

$storeId = $_GET['id'] ?? null;
$db = Database::getInstance();

if (!$storeId) {
    header('Location: ?page=subscribers');
    exit;
}

$storeModel = new Store();
$store = $storeModel->getWithLegalEntity($storeId);

if (!$store) {
    $_SESSION['error'] = 'Store not found';
    header('Location: ?page=subscribers');
    exit;
}

// Get camera installations
$cameraInstallationModel = new CameraInstallation();
$installations = $cameraInstallationModel->getByStore($storeId);

// Get active camera count
$activeCameras = $storeModel->getActiveCameraCount($storeId);

// Camera snapshots table was removed in Migration 023
// We now use camera_installations table for tracking cameras
$snapshots = [];

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Store Details</h1>
        <a href="?page=subscribers&action=edit&id=<?= $store['legal_entity_id'] ?>" class="btn">← Back to Legal Entity</a>
    </div>

    <!-- Store Information -->
    <div class="card" style="margin-bottom: 20px;">
        <h2><?= htmlspecialchars($store['store_name']) ?></h2>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px;">
            <div>
                <strong>Store ID:</strong> <?= htmlspecialchars($store['store_id']) ?>
            </div>
            <div>
                <strong>Legal Entity:</strong> 
                <a href="?page=subscribers&action=view&id=<?= $store['legal_entity_id'] ?>">
                    <?= htmlspecialchars($store['legal_entity_name']) ?>
                </a>
            </div>
            <div>
                <strong>Installation Date:</strong> 
                <?= $store['installation_date'] ? date('d/m/Y', strtotime($store['installation_date'])) : 'N/A' ?>
            </div>
            <div>
                <strong>Category:</strong> <?= htmlspecialchars($store['category'] ?? 'N/A') ?>
            </div>
            <div>
                <strong>Active Main Cameras:</strong> <?= number_format($activeCameras['main_cameras'] ?? 0) ?>
            </div>
            <div>
                <strong>Active Additional Cameras:</strong> <?= number_format($activeCameras['additional_cameras'] ?? 0) ?>
            </div>
        </div>
        <div style="margin-top: 15px; display: flex; gap: 10px;">
            <a href="?page=stores&action=edit&id=<?= $store['id'] ?>" class="btn">Edit Store</a>
            <a href="?page=stores&action=camera_history&store_id=<?= $store['id'] ?>" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                📹 View Camera History
            </a>
        </div>
    </div>


    <!-- Camera Installations -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <div>
                <h2 style="margin: 0;">🎥 Camera Installations</h2>
                <p style="color: #666; margin: 5px 0 0 0;">
                    Incremental camera installation and removal history.
                </p>
            </div>
            <a href="?page=cameras&action=new&store_id=<?= $storeId ?>" class="btn btn-success">+ Add Camera Installation</a>
        </div>

        <?php if (!empty($installations)): ?>
        <table>
            <thead>
                <tr>
                    <th>Installation Date</th>
                    <th>Camera Type</th>
                    <th>Camera Name</th>
                    <th>SAFR Code</th>
                    <th>Invoice Number</th>
                    <th>Removal Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installations as $installation): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($installation['installation_date'])) ?></td>
                    <td><?= ucfirst($installation['camera_type']) ?></td>
                    <td>
                        <?php if (!empty($installation['camera_name'])): ?>
                            <?= htmlspecialchars($installation['camera_name']) ?>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;">Not set</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($installation['safr_code'])): ?>
                            <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;"><?= htmlspecialchars($installation['safr_code']) ?></code>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($installation['invoice_number'] ?? 'N/A') ?></td>
                    <td><?= $installation['removal_date'] ? date('d/m/Y', strtotime($installation['removal_date'])) : '-' ?></td>
                    <td>
                        <?php if ($installation['removal_date']): ?>
                            <span style="color: red;">Removed</span>
                        <?php else: ?>
                            <span style="color: green; font-weight: bold;">Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=cameras&action=edit&id=<?= $installation['id'] ?>" class="btn btn-sm">Edit</a>
                        <a href="?page=cameras&action=delete&id=<?= $installation['id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure you want to delete this camera installation?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color: #999;">No camera installations recorded for this store yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

