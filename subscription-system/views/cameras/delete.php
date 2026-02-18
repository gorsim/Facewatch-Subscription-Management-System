<?php
/**
 * Delete Camera Installation
 */

use App\Database;
use App\Services\CameraValidationService;

$pageTitle = 'Delete Camera Installation';
$page = 'subscribers';

$id = $_GET['id'] ?? null;
$db = Database::getInstance();

if (!$id) {
    $_SESSION['error'] = 'Camera installation ID is required';
    header('Location: ?page=dashboard');
    exit;
}

// Get the camera installation to find the store_id for redirect
$installation = $db->fetchOne(
    "SELECT * FROM camera_installations WHERE id = :id",
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
        // Delete the camera installation
        $db->delete('camera_installations', 'id = :id', ['id' => $id]);

        $_SESSION['success'] = 'Camera installation deleted successfully';

        // Redirect back to the store view page
        header("Location: ?page=stores&action=view&id={$storeId}");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error deleting camera installation: ' . $e->getMessage();
        header("Location: ?page=stores&action=view&id={$storeId}");
        exit;
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <h1>Delete Camera Installation</h1>
    
    <div class="card">
        <h2>Confirm Deletion</h2>
        <p>Are you sure you want to delete this camera installation?</p>
        
        <table style="width: 100%; margin: 20px 0;">
            <tr>
                <td style="width: 200px; font-weight: bold;">Installation Date:</td>
                <td><?= date('d/m/Y', strtotime($installation['installation_date'])) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Camera Type:</td>
                <td><?= ucfirst($installation['camera_type']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Invoice Number:</td>
                <td><?= htmlspecialchars($installation['invoice_number'] ?? 'N/A') ?></td>
            </tr>
            <?php if ($installation['removal_date']): ?>
            <tr>
                <td style="font-weight: bold;">Removal Date:</td>
                <td><?= date('d/m/Y', strtotime($installation['removal_date'])) ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <strong>⚠️ Warning:</strong> This action cannot be undone. The camera installation record will be permanently deleted.
        </div>
        
        <form method="POST" style="margin-top: 20px;">
            <button type="submit" class="btn btn-danger">Yes, Delete Camera Installation</button>
            <a href="?page=stores&action=view&id=<?= $storeId ?>" class="btn">Cancel</a>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

