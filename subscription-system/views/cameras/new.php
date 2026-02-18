<?php
/**
 * Add New Camera Installation
 */

use App\Database;
use App\Services\CameraValidationService;

$pageTitle = 'Add Camera Installation';
$page = 'subscribers';

$storeId = $_GET['store_id'] ?? null;
$db = Database::getInstance();

if (!$storeId) {
    $_SESSION['error'] = 'Store ID is required';
    header('Location: ?page=dashboard');
    exit;
}

// Get store details
$store = $db->fetchOne(
    "SELECT s.*, le.legal_entity_name
     FROM stores s
     JOIN legal_entities le ON s.legal_entity_id = le.id
     WHERE s.id = :id",
    ['id' => $storeId]
);

if (!$store) {
    $_SESSION['error'] = 'Store not found';
    header('Location: ?page=dashboard');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Insert new camera installation
        $db->insert('camera_installations', [
            'store_id' => $storeId,
            'installation_date' => $_POST['installation_date'],
            'camera_type' => $_POST['camera_type'],
            'camera_name' => $_POST['camera_name'] ?: null,
            'removal_date' => $_POST['removal_date'] ?: null,
        ]);

        $_SESSION['success'] = 'Camera installation added successfully';
        header("Location: ?page=stores&action=view&id={$storeId}");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = 'Error adding camera installation: ' . $e->getMessage();
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <h1>Add Camera Installation</h1>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h2><?= htmlspecialchars($store['store_name']) ?> (<?= htmlspecialchars($store['store_id']) ?>)</h2>
        <p style="color: #666; margin-top: -10px;">
            Legal Entity: <?= htmlspecialchars($store['legal_entity_name']) ?>
        </p>
        
        <form method="POST">
            <div class="form-group">
                <label for="installation_date">Installation Date *</label>
                <input type="date" 
                       id="installation_date" 
                       name="installation_date" 
                       value="<?= date('Y-m-d') ?>"
                       required>
            </div>
            
            <div class="form-group">
                <label for="camera_type">Camera Type *</label>
                <select id="camera_type" name="camera_type" required>
                    <option value="">-- Select Camera Type --</option>
                    <option value="main">Main</option>
                    <option value="additional">Additional</option>
                </select>
                <small style="color: #666;">
                    Main cameras are the primary cameras, additional cameras are supplementary
                </small>
            </div>

            <div class="form-group">
                <label for="camera_name">Camera Name</label>
                <input type="text"
                       id="camera_name"
                       name="camera_name"
                       maxlength="100"
                       placeholder="e.g., Front door, Back door, Till area">
                <small style="color: #666;">
                    Optional: Give this camera a descriptive name to identify its location
                </small>
            </div>

            <div class="form-group">
                <label for="removal_date">Removal Date</label>
                <input type="date"
                       id="removal_date"
                       name="removal_date">
                <small style="color: #666;">Leave blank if camera is currently active</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">Add Camera Installation</button>
                <a href="?page=stores&action=view&id=<?= $storeId ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

