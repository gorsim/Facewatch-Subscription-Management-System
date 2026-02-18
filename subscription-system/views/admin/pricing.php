<?php
/**
 * Camera Pricing Management
 * Admin interface to manage volume-based pricing tiers
 */

use App\Database;

$pageTitle = 'Camera Pricing Management';
$page = 'admin';

$db = Database::getInstance();

// Handle form submission (add/edit pricing tier)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_pricing') {
        try {
            $data = [
                'effective_date' => $_POST['effective_date'],
                'min_cameras' => (int) $_POST['min_cameras'],
                'max_cameras' => !empty($_POST['max_cameras']) ? (int) $_POST['max_cameras'] : null,
                'price_per_annum' => (float) $_POST['price_per_annum'],
                'price_per_quarter' => (float) $_POST['price_per_quarter'],
                'price_per_month' => (float) $_POST['price_per_month'],
                'notes' => $_POST['notes'] ?? ''
            ];
            
            if (!empty($_POST['id'])) {
                // Update existing
                $db->query(
                    "UPDATE camera_pricing 
                     SET effective_date = :effective_date,
                         min_cameras = :min_cameras,
                         max_cameras = :max_cameras,
                         price_per_annum = :price_per_annum,
                         price_per_quarter = :price_per_quarter,
                         price_per_month = :price_per_month,
                         notes = :notes
                     WHERE id = :id",
                    array_merge($data, ['id' => $_POST['id']])
                );
                $_SESSION['success'] = 'Pricing tier updated successfully!';
            } else {
                // Insert new
                $db->insert('camera_pricing', $data);
                $_SESSION['success'] = 'Pricing tier added successfully!';
            }
            
            header('Location: ?page=admin&action=pricing');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Failed to save pricing: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_pricing' && !empty($_POST['id'])) {
        try {
            $db->query("DELETE FROM camera_pricing WHERE id = :id", ['id' => $_POST['id']]);
            $_SESSION['success'] = 'Pricing tier deleted successfully!';
            header('Location: ?page=admin&action=pricing');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Failed to delete pricing: ' . $e->getMessage();
        }
    }
}

// Get all pricing tiers grouped by effective date
$pricingByDate = [];
$allPricing = $db->fetchAll(
    "SELECT * FROM camera_pricing 
     ORDER BY effective_date DESC, min_cameras ASC"
);

foreach ($allPricing as $pricing) {
    $date = $pricing['effective_date'];
    if (!isset($pricingByDate[$date])) {
        $pricingByDate[$date] = [];
    }
    $pricingByDate[$date][] = $pricing;
}

// Get pricing tier for editing (if requested)
$editPricing = null;
if (isset($_GET['edit_id'])) {
    $editPricing = $db->fetchOne(
        "SELECT * FROM camera_pricing WHERE id = :id",
        ['id' => $_GET['edit_id']]
    );
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1>📊 Camera Pricing Management</h1>
            <p style="color: #666; margin-top: 5px;">
                Manage default volume-based pricing tiers.
                <a href="?page=admin&action=pricing_dashboard">View Pricing Dashboard →</a> |
                <a href="?page=admin&action=independent_pricing">Manage Independent Pricing →</a> |
                <a href="?page=admin&action=import_pricing" style="color: #27ae60; font-weight: bold;">📥 Import from CSV →</a>
            </p>
        </div>
        <a href="?page=admin" class="btn">← Back to Admin</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="card" style="margin-bottom: 30px;">
        <h2><?= $editPricing ? '✏️ Edit' : '➕ Add New' ?> Pricing Tier</h2>
        <form method="POST">
            <input type="hidden" name="action" value="save_pricing">
            <?php if ($editPricing): ?>
                <input type="hidden" name="id" value="<?= $editPricing['id'] ?>">
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                <div class="form-group">
                    <label for="effective_date">Effective Date *</label>
                    <input type="date" id="effective_date" name="effective_date" 
                           value="<?= $editPricing['effective_date'] ?? date('Y-01-01') ?>" required>
                    <small>Usually January 1st of each year</small>
                </div>

                <div class="form-group">
                    <label for="min_cameras">Min Cameras *</label>
                    <input type="number" id="min_cameras" name="min_cameras" 
                           value="<?= $editPricing['min_cameras'] ?? '' ?>" required min="0">
                </div>

                <div class="form-group">
                    <label for="max_cameras">Max Cameras</label>
                    <input type="number" id="max_cameras" name="max_cameras" 
                           value="<?= $editPricing['max_cameras'] ?? '' ?>" min="0">
                    <small>Leave empty for unlimited</small>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px;">
                <div class="form-group">
                    <label for="price_per_annum">Price Per Annum (£) *</label>
                    <input type="number" step="0.01" id="price_per_annum" name="price_per_annum" 
                           value="<?= $editPricing['price_per_annum'] ?? '' ?>" required min="0">
                </div>

                <div class="form-group">
                    <label for="price_per_quarter">Price Per Quarter (£) *</label>
                    <input type="number" step="0.01" id="price_per_quarter" name="price_per_quarter" 
                           value="<?= $editPricing['price_per_quarter'] ?? '' ?>" required min="0">
                </div>

                <div class="form-group">
                    <label for="price_per_month">Price Per Month (£) *</label>
                    <input type="number" step="0.01" id="price_per_month" name="price_per_month" 
                           value="<?= $editPricing['price_per_month'] ?? '' ?>" required min="0">
                </div>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label for="notes">Notes</label>
                <input type="text" id="notes" name="notes" 
                       value="<?= htmlspecialchars($editPricing['notes'] ?? '') ?>" 
                       placeholder="e.g., Tier 1: 1-49 cameras">
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <?= $editPricing ? '💾 Update' : '➕ Add' ?> Pricing Tier
                </button>
                <?php if ($editPricing): ?>
                    <a href="?page=admin&action=pricing" class="btn">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Pricing Tiers by Effective Date -->
    <?php foreach ($pricingByDate as $date => $tiers): ?>
        <div class="card" style="margin-bottom: 20px;">
            <h2>📅 Pricing Effective from <?= date('d M Y', strtotime($date)) ?></h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Camera Range</th>
                        <th>Price Per Annum</th>
                        <th>Price Per Quarter</th>
                        <th>Price Per Month</th>
                        <th>Notes</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tiers as $tier): ?>
                        <tr>
                            <td>
                                <strong><?= $tier['min_cameras'] ?>-<?= $tier['max_cameras'] ?? '∞' ?></strong> cameras
                            </td>
                            <td>£<?= number_format($tier['price_per_annum'], 0) ?></td>
                            <td>£<?= number_format($tier['price_per_quarter'], 0) ?></td>
                            <td>£<?= number_format($tier['price_per_month'], 0) ?></td>
                            <td><?= htmlspecialchars($tier['notes']) ?></td>
                            <td>
                                <a href="?page=admin&action=pricing&edit_id=<?= $tier['id'] ?>" 
                                   class="btn btn-sm">✏️ Edit</a>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this pricing tier?');">
                                    <input type="hidden" name="action" value="delete_pricing">
                                    <input type="hidden" name="id" value="<?= $tier['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <?php if (empty($pricingByDate)): ?>
        <div class="card">
            <p style="text-align: center; color: #999; padding: 40px;">
                No pricing tiers defined yet. Add your first pricing tier above.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

