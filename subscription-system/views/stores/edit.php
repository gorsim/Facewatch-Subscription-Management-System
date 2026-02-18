<?php
/**
 * Edit Store
 */

use App\Models\Store;
use App\Models\LegalEntity;
use App\Database;

$pageTitle = 'Edit Store';
$page = 'subscribers';

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

// Get legal entity
$legalEntityModel = new LegalEntity();
$legalEntity = $legalEntityModel->find($store['legal_entity_id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update store
        $db->update('stores', [
            'store_id' => $_POST['store_id'],
            'store_name' => $_POST['store_name'],
            'installation_date' => $_POST['installation_date'] ?: null,
            'termination_date' => $_POST['termination_date'] ?: null,
            'category' => $_POST['category'],
        ], 'id = :id', ['id' => $storeId]);
        
        $_SESSION['success'] = 'Store updated successfully';
        header('Location: ?page=subscribers&action=edit&id=' . $store['legal_entity_id']);
        exit;
        
    } catch (Exception $e) {
        $error = 'Failed to update store: ' . $e->getMessage();
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Edit Store</h1>
        <a href="?page=subscribers&action=edit&id=<?= $store['legal_entity_id'] ?>" class="btn">← Back to Legal Entity</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <h3>Store Information</h3>
            
            <div class="form-group">
                <label>Legal Entity</label>
                <input type="text" value="<?= htmlspecialchars($legalEntity['legal_entity_name']) ?>" disabled>
                <small>Cannot change legal entity. Create a new store instead.</small>
            </div>
            
            <div class="form-group">
                <label for="store_id">Store ID *</label>
                <input type="text" id="store_id" name="store_id" 
                       value="<?= htmlspecialchars($store['store_id']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="store_name">Store Name *</label>
                <input type="text" id="store_name" name="store_name" 
                       value="<?= htmlspecialchars($store['store_name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="installation_date">Installation Date</label>
                <input type="date" id="installation_date" name="installation_date" 
                       value="<?= $store['installation_date'] ?? '' ?>">
            </div>
            
            <div class="form-group">
                <label for="termination_date">Termination Date</label>
                <input type="date" id="termination_date" name="termination_date" 
                       value="<?= $store['termination_date'] ?? '' ?>">
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" 
                       value="<?= htmlspecialchars($store['category'] ?? '') ?>"
                       placeholder="e.g., Retail, Wholesale, Warehouse">
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <a href="?page=subscribers&action=edit&id=<?= $store['legal_entity_id'] ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

