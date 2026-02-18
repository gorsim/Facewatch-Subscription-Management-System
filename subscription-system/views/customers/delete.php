<?php
/**
 * Delete Legal Entity
 */

use App\Models\LegalEntity;
use App\Database;

$legalEntityId = $_GET['id'] ?? null;
$db = Database::getInstance();

if (!$legalEntityId) {
    header('Location: ?page=subscribers');
    exit;
}

$legalEntityModel = new LegalEntity();
$legalEntity = $legalEntityModel->find($legalEntityId);

if (!$legalEntity) {
    $_SESSION['error'] = 'Legal Entity not found';
    header('Location: ?page=subscribers');
    exit;
}

// Check for associated data
$storeCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM stores WHERE legal_entity_id = :id",
    ['id' => $legalEntityId]
);

$invoiceCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM invoices WHERE legal_entity_id = :id",
    ['id' => $legalEntityId]
);

$cameraCount = $db->fetchOne(
    "SELECT COUNT(*) as count 
     FROM camera_installations ci
     JOIN stores s ON ci.store_id = s.id
     WHERE s.legal_entity_id = :id",
    ['id' => $legalEntityId]
);

// If there's associated data, show warning
if ($storeCount['count'] > 0 || $invoiceCount['count'] > 0 || $cameraCount['count'] > 0) {
    $pageTitle = 'Delete Legal Entity - Warning';
    $page = 'subscribers';
    require __DIR__ . '/../layouts/header.php';
    ?>
    
    <div class="container">
        <div class="card" style="border: 3px solid #dc3545; background-color: #fff3cd;">
            <h1 style="color: #dc3545;">⚠️ Cannot Delete Legal Entity</h1>

            <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <p style="margin: 0; font-size: 18px; font-weight: bold; color: #721c24;">
                    ❌ Deletion Failed - Legal Entity NOT Deleted
                </p>
            </div>

            <p><strong><?= htmlspecialchars($legalEntity['legal_entity_name']) ?></strong> has associated data that must be removed first:</p>

            <ul style="font-size: 16px; line-height: 1.8;">
                <?php if ($storeCount['count'] > 0): ?>
                <li><strong><?= $storeCount['count'] ?> store(s)</strong> - Please delete all stores first</li>
                <?php endif; ?>

                <?php if ($cameraCount['count'] > 0): ?>
                <li><strong><?= $cameraCount['count'] ?> camera installation(s)</strong> - Please remove all cameras first</li>
                <?php endif; ?>

                <?php if ($invoiceCount['count'] > 0): ?>
                <li><strong><?= $invoiceCount['count'] ?> invoice(s)</strong> - Cannot delete legal entities with invoices</li>
                <?php endif; ?>
            </ul>

            <p style="font-weight: bold; color: #721c24;">⚠️ Please remove all associated data before deleting this legal entity.</p>
            
            <div style="margin-top: 20px;">
                <a href="?page=subscribers&action=view&id=<?= $legalEntity['id'] ?>" class="btn">← Back to Legal Entity</a>
                <a href="?page=subscribers" class="btn">← Back to List</a>
            </div>
        </div>
    </div>
    
    <?php
    require __DIR__ . '/../layouts/footer.php';
    exit;
}

// If we get here, safe to delete
try {
    $db->beginTransaction();
    
    // Delete contract
    $db->delete('legal_entity_contracts', 'legal_entity_id = :id', ['id' => $legalEntityId]);
    
    // Delete legal entity
    $db->delete('legal_entities', 'id = :id', ['id' => $legalEntityId]);
    
    $db->commit();
    
    $_SESSION['success'] = 'Legal Entity deleted successfully';
    header('Location: ?page=subscribers');
    exit;
    
} catch (Exception $e) {
    $db->rollback();
    $_SESSION['error'] = 'Failed to delete legal entity: ' . $e->getMessage();
    header('Location: ?page=subscribers&action=view&id=' . $legalEntityId);
    exit;
}

