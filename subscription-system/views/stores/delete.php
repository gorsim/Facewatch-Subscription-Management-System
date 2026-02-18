<?php
/**
 * Delete Store
 */

use App\Models\Store;
use App\Database;

$storeId = $_GET['id'] ?? null;
$db = Database::getInstance();

if (!$storeId) {
    header('Location: ?page=subscribers');
    exit;
}

$storeModel = new Store();
$store = $storeModel->find($storeId);

if (!$store) {
    $_SESSION['error'] = 'Store not found';
    header('Location: ?page=subscribers');
    exit;
}

$legalEntityId = $store['legal_entity_id'];

// Check if store has camera installations
$cameraCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM camera_installations WHERE store_id = :store_id",
    ['store_id' => $storeId]
);

if ($cameraCount['count'] > 0) {
    $_SESSION['error'] = 'Cannot delete store with camera installations. Remove cameras first.';
    header('Location: ?page=subscribers&action=edit&id=' . $legalEntityId);
    exit;
}

try {
    // Delete the store
    $db->delete('stores', 'id = :id', ['id' => $storeId]);
    
    $_SESSION['success'] = 'Store deleted successfully';
    header('Location: ?page=subscribers&action=edit&id=' . $legalEntityId);
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to delete store: ' . $e->getMessage();
    header('Location: ?page=subscribers&action=edit&id=' . $legalEntityId);
    exit;
}

