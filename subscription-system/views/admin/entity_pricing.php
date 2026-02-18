<?php
/**
 * Entity-Specific Camera Pricing Management
 * Admin interface to manage custom pricing tiers for a specific legal entity
 */

use App\Database;

$pageTitle = 'Entity-Specific Pricing';
$page = 'admin';

$db = Database::getInstance();
$legalEntityId = $_GET['id'] ?? null;

if (!$legalEntityId) {
    $_SESSION['error'] = 'Legal Entity ID is required';
    header('Location: ?page=subscribers');
    exit;
}

// Get legal entity details
$legalEntity = $db->fetchOne(
    "SELECT * FROM legal_entities WHERE id = :id",
    ['id' => $legalEntityId]
);

if (!$legalEntity) {
    $_SESSION['error'] = 'Legal Entity not found';
    header('Location: ?page=subscribers');
    exit;
}

// Handle form submission (add/edit pricing tier)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_pricing') {
        try {
            $data = [
                'legal_entity_id' => $legalEntityId,
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
                    "UPDATE legal_entity_pricing 
                     SET effective_date = :effective_date,
                         min_cameras = :min_cameras,
                         max_cameras = :max_cameras,
                         price_per_annum = :price_per_annum,
                         price_per_quarter = :price_per_quarter,
                         price_per_month = :price_per_month,
                         notes = :notes
                     WHERE id = :id AND legal_entity_id = :legal_entity_id",
                    array_merge($data, ['id' => $_POST['id']])
                );
                $_SESSION['success'] = 'Pricing tier updated successfully!';
            } else {
                // Insert new
                $db->insert('legal_entity_pricing', $data);
                $_SESSION['success'] = 'Pricing tier added successfully!';
            }
            
            header("Location: ?page=admin&action=entity_pricing&id=$legalEntityId");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error saving pricing tier: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_pricing' && !empty($_POST['id'])) {
        try {
            $db->query(
                "DELETE FROM legal_entity_pricing WHERE id = :id AND legal_entity_id = :legal_entity_id",
                ['id' => $_POST['id'], 'legal_entity_id' => $legalEntityId]
            );
            $_SESSION['success'] = 'Pricing tier deleted successfully!';
            header("Location: ?page=admin&action=entity_pricing&id=$legalEntityId");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error deleting pricing tier: ' . $e->getMessage();
        }
    }
}

// Get all pricing tiers for this entity
$pricingTiers = $db->fetchAll(
    "SELECT * FROM legal_entity_pricing 
     WHERE legal_entity_id = :legal_entity_id
     ORDER BY effective_date DESC, min_cameras ASC",
    ['legal_entity_id' => $legalEntityId]
);

// Group by effective date
$tiersByDate = [];
foreach ($pricingTiers as $tier) {
    $tiersByDate[$tier['effective_date']][] = $tier;
}

// Get tier to edit if specified
$editTier = null;
if (isset($_GET['edit'])) {
    $editTier = $db->fetchOne(
        "SELECT * FROM legal_entity_pricing WHERE id = :id AND legal_entity_id = :legal_entity_id",
        ['id' => $_GET['edit'], 'legal_entity_id' => $legalEntityId]
    );
}

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🎯 Custom Pricing: <?= htmlspecialchars($legalEntity['legal_entity_name']) ?></h1>
        <p style="color: #666;">
            <a href="?page=subscribers&action=view&id=<?= $legalEntityId ?>">← Back to Legal Entity</a> | 
            <a href="?page=subscribers&action=edit&id=<?= $legalEntityId ?>">Edit Entity Details</a>
        </p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2><?= $editTier ? '✏️ Edit' : '➕ Add New' ?> Pricing Tier</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="save_pricing">
            <?php if ($editTier): ?>
                <input type="hidden" name="id" value="<?= $editTier['id'] ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="effective_date">Effective Date *</label>
                    <input type="date" id="effective_date" name="effective_date"
                           value="<?= $editTier['effective_date'] ?? date('Y-01-01') ?>" required>
                    <small>Date when this pricing becomes active</small>
                </div>

                <div class="form-group">
                    <label for="min_cameras">Min Cameras *</label>
                    <input type="number" id="min_cameras" name="min_cameras" min="1"
                           value="<?= $editTier['min_cameras'] ?? '1' ?>" required>
                </div>

                <div class="form-group">
                    <label for="max_cameras">Max Cameras</label>
                    <input type="number" id="max_cameras" name="max_cameras" min="1"
                           value="<?= $editTier['max_cameras'] ?? '' ?>"
                           placeholder="Leave blank for unlimited">
                    <small>Leave blank for unlimited (500+)</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="price_per_annum">Price Per Annum (£) *</label>
                    <input type="number" step="0.01" id="price_per_annum" name="price_per_annum"
                           value="<?= $editTier['price_per_annum'] ?? '' ?>" required>
                </div>

                <div class="form-group">
                    <label for="price_per_quarter">Price Per Quarter (£) *</label>
                    <input type="number" step="0.01" id="price_per_quarter" name="price_per_quarter"
                           value="<?= $editTier['price_per_quarter'] ?? '' ?>" required>
                </div>

                <div class="form-group">
                    <label for="price_per_month">Price Per Month (£) *</label>
                    <input type="number" step="0.01" id="price_per_month" name="price_per_month"
                           value="<?= $editTier['price_per_month'] ?? '' ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2"><?= htmlspecialchars($editTier['notes'] ?? '') ?></textarea>
            </div>

            <div style="margin-top: 15px;">
                <button type="submit" class="btn btn-success">
                    <?= $editTier ? '💾 Update Tier' : '➕ Add Tier' ?>
                </button>
                <?php if ($editTier): ?>
                    <a href="?page=admin&action=entity_pricing&id=<?= $legalEntityId ?>" class="btn">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Existing Pricing Tiers -->
    <?php if (!empty($tiersByDate)): ?>
        <div class="card" style="margin-top: 30px;">
            <h2>📊 Current Pricing Tiers</h2>

            <?php foreach ($tiersByDate as $effectiveDate => $tiers): ?>
                <div style="margin-bottom: 30px;">
                    <h3 style="color: #2563eb; margin-bottom: 15px;">
                        📅 Effective from <?= date('d M Y', strtotime($effectiveDate)) ?>
                    </h3>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Camera Range</th>
                                <th>Price Per Annum</th>
                                <th>Price Per Quarter</th>
                                <th>Price Per Month</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tiers as $tier): ?>
                                <tr>
                                    <td>
                                        <strong><?= $tier['min_cameras'] ?> - <?= $tier['max_cameras'] ?? '∞' ?></strong>
                                        cameras
                                    </td>
                                    <td>£<?= number_format($tier['price_per_annum'], 2) ?></td>
                                    <td>£<?= number_format($tier['price_per_quarter'], 2) ?></td>
                                    <td>£<?= number_format($tier['price_per_month'], 2) ?></td>
                                    <td><?= htmlspecialchars($tier['notes']) ?></td>
                                    <td>
                                        <a href="?page=admin&action=entity_pricing&id=<?= $legalEntityId ?>&edit=<?= $tier['id'] ?>"
                                           class="btn btn-sm">✏️ Edit</a>
                                        <form method="POST" style="display: inline;"
                                              onsubmit="return confirm('Delete this pricing tier?');">
                                            <input type="hidden" name="action" value="delete_pricing">
                                            <input type="hidden" name="id" value="<?= $tier['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">🗑️ Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card" style="margin-top: 30px;">
            <p style="text-align: center; color: #666; padding: 40px;">
                📋 No custom pricing tiers defined yet. Add your first tier above.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

