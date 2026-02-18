<?php
/**
 * Create New Store
 */

use App\Models\Store;
use App\Models\LegalEntity;
use App\Database;

$pageTitle = 'New Store';
$page = 'subscribers';

$legalEntityId = $_GET['legal_entity_id'] ?? null;
$db = Database::getInstance();

if (!$legalEntityId) {
    header('Location: ?page=subscribers');
    exit;
}

// Get legal entity
$legalEntityModel = new LegalEntity();
$legalEntity = $legalEntityModel->find($legalEntityId);

if (!$legalEntity) {
    $_SESSION['error'] = 'Legal Entity not found';
    header('Location: ?page=subscribers');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create store
        $db->insert('stores', [
            'legal_entity_id' => $legalEntityId,
            'store_id' => $_POST['store_id'],
            'store_name' => $_POST['store_name'],
            'installation_date' => $_POST['installation_date'] ?: null,
            'category' => $_POST['category'],
        ]);
        
        $_SESSION['success'] = 'Store created successfully';
        header('Location: ?page=subscribers&action=edit&id=' . $legalEntityId);
        exit;
        
    } catch (Exception $e) {
        $error = 'Failed to create store: ' . $e->getMessage();
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Create New Store</h1>
        <a href="?page=subscribers&action=edit&id=<?= $legalEntityId ?>" class="btn">← Back to Legal Entity</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <h3>Store Information</h3>
            
            <div class="form-group">
                <label>Legal Entity</label>
                <input type="text" value="<?= htmlspecialchars($legalEntity['legal_entity_name']) ?> (<?= htmlspecialchars($legalEntity['legal_entity_id']) ?>)" disabled>
            </div>
            
            <div class="form-group">
                <label for="store_id">Store ID *</label>
                <input type="text" id="store_id" name="store_id" required
                       placeholder="e.g., <?= htmlspecialchars($legalEntity['legal_entity_id']) ?>-001">
                <small>Suggested format: <?= htmlspecialchars($legalEntity['legal_entity_id']) ?>-001, <?= htmlspecialchars($legalEntity['legal_entity_id']) ?>-002, etc.</small>
            </div>
            
            <div class="form-group">
                <label for="store_name">Store Name *</label>
                <input type="text" id="store_name" name="store_name" required
                       placeholder="e.g., <?= htmlspecialchars($legalEntity['legal_entity_name']) ?> - Main Store">
            </div>
            
            <div class="form-group">
                <label for="installation_date">Installation Date</label>
                <input type="date" id="installation_date" name="installation_date">
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" 
                       placeholder="e.g., Retail, Wholesale, Warehouse">
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">Create Store</button>
                <a href="?page=subscribers&action=edit&id=<?= $legalEntityId ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

