<?php
/**
 * Edit Camera Installation
 */

use App\Database;
use App\Services\CameraValidationService;

$pageTitle = 'Edit Camera Installation';
$page = 'subscribers';

$id = $_GET['id'] ?? null;
$db = Database::getInstance();

if (!$id) {
    $_SESSION['error'] = 'Camera installation ID is required';
    header('Location: ?page=dashboard');
    exit;
}

// Get the camera installation with store details
$installation = $db->fetchOne(
    "SELECT ci.*, s.store_name, s.store_id as store_external_id
     FROM camera_installations ci
     JOIN stores s ON ci.store_id = s.id
     WHERE ci.id = :id",
    ['id' => $id]
);

if (!$installation) {
    $_SESSION['error'] = 'Camera installation not found';
    header('Location: ?page=dashboard');
    exit;
}

$storeId = $installation['store_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update camera installation
        $db->update('camera_installations', [
            'installation_date' => $_POST['installation_date'],
            'camera_type' => $_POST['camera_type'],
            'camera_name' => $_POST['camera_name'] ?: null,
            'safr_code' => $_POST['safr_code'] ?: null,
            'removal_date' => $_POST['removal_date'] ?: null,
        ], 'id = :id', ['id' => $id]);

        $_SESSION['success'] = 'Camera installation updated successfully';
        header("Location: ?page=stores&action=view&id={$storeId}");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = 'Error updating camera installation: ' . $e->getMessage();
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <h1>Edit Camera Installation</h1>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h2><?= htmlspecialchars($installation['store_name']) ?> (<?= htmlspecialchars($installation['store_external_id']) ?>)</h2>
        
        <form method="POST">
            <div class="form-group">
                <label for="installation_date">Installation Date *</label>
                <input type="date" 
                       id="installation_date" 
                       name="installation_date" 
                       value="<?= htmlspecialchars($installation['installation_date']) ?>"
                       required>
            </div>
            
            <div class="form-group">
                <label for="camera_type">Camera Type *</label>
                <select id="camera_type" name="camera_type" required>
                    <option value="main" <?= $installation['camera_type'] === 'main' ? 'selected' : '' ?>>Main</option>
                    <option value="additional" <?= $installation['camera_type'] === 'additional' ? 'selected' : '' ?>>Additional</option>
                </select>
            </div>

            <div class="form-group">
                <label for="camera_name">Camera Name</label>
                <input type="text"
                       id="camera_name"
                       name="camera_name"
                       maxlength="100"
                       value="<?= htmlspecialchars($installation['camera_name'] ?? '') ?>"
                       placeholder="e.g., Front door, Back door, Till area">
                <small style="color: #666;">
                    Optional: Give this camera a descriptive name to identify its location
                </small>
            </div>

            <div class="form-group">
                <label for="safr_code">SAFR Code</label>
                <input type="text"
                       id="safr_code"
                       name="safr_code"
                       maxlength="100"
                       value="<?= htmlspecialchars($installation['safr_code'] ?? '') ?>"
                       placeholder="e.g., SAFR-001, SAFR-002">
                <small style="color: #666;">
                    Optional: SAFR system identifier for this camera
                </small>
            </div>

            <div class="form-group">
                <label for="removal_date">Removal Date</label>
                <input type="date"
                       id="removal_date"
                       name="removal_date"
                       value="<?= htmlspecialchars($installation['removal_date'] ?? '') ?>">
                <small style="color: #666;">Leave blank if camera is still active</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">Update Camera Installation</button>
                <a href="?page=stores&action=view&id=<?= $storeId ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

