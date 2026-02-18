<?php
/**
 * Independent Pricing Management
 * Manage default "first camera + additional" pricing for independent entities
 */

use App\Database;

$pageTitle = 'Independent Pricing';
$page = 'pricing';

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_pricing') {
        try {
            $data = [
                'effective_date' => $_POST['effective_date'],
                'first_camera_price_per_annum' => $_POST['first_camera_price_per_annum'],
                'first_camera_price_per_quarter' => $_POST['first_camera_price_per_quarter'],
                'first_camera_price_per_month' => $_POST['first_camera_price_per_month'],
                'additional_camera_price_per_annum' => $_POST['additional_camera_price_per_annum'],
                'additional_camera_price_per_quarter' => $_POST['additional_camera_price_per_quarter'],
                'additional_camera_price_per_month' => $_POST['additional_camera_price_per_month'],
                'notes' => $_POST['notes'] ?? null
            ];

            if (!empty($_POST['id'])) {
                // Update existing
                $db->query(
                    "UPDATE default_independent_pricing
                     SET effective_date = :effective_date,
                         first_camera_price_per_annum = :first_camera_price_per_annum,
                         first_camera_price_per_quarter = :first_camera_price_per_quarter,
                         first_camera_price_per_month = :first_camera_price_per_month,
                         additional_camera_price_per_annum = :additional_camera_price_per_annum,
                         additional_camera_price_per_quarter = :additional_camera_price_per_quarter,
                         additional_camera_price_per_month = :additional_camera_price_per_month,
                         notes = :notes
                     WHERE id = :id",
                    array_merge($data, ['id' => $_POST['id']])
                );
                $_SESSION['success'] = 'Independent pricing updated successfully!';
            } else {
                // Insert new
                $db->insert('default_independent_pricing', $data);
                $_SESSION['success'] = 'Independent pricing added successfully!';
            }

            header('Location: ?page=admin&action=independent_pricing');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error saving pricing: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_pricing' && !empty($_POST['id'])) {
        try {
            $db->query("DELETE FROM default_independent_pricing WHERE id = :id", ['id' => $_POST['id']]);
            $_SESSION['success'] = 'Independent pricing deleted successfully!';
            header('Location: ?page=admin&action=independent_pricing');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error deleting pricing: ' . $e->getMessage();
        }
    }
}

// Get pricing for editing (if requested)
$editPricing = null;
if (isset($_GET['edit_id'])) {
    $editPricing = $db->fetchOne(
        "SELECT * FROM default_independent_pricing WHERE id = :id",
        ['id' => $_GET['edit_id']]
    );
}

// Get all default independent pricing
$pricingHistory = $db->fetchAll(
    "SELECT * FROM default_independent_pricing ORDER BY effective_date DESC"
);

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1>📸 Independent Pricing Management</h1>
            <p style="color: #666; margin-top: 5px;">
                Manage default pricing for independent entities using the "First Camera + Additional" model
            </p>
        </div>
        <a href="?page=admin&action=pricing" class="btn">← Back to Volume Pricing</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card">
        <h2>💡 How Independent Pricing Works</h2>
        <p>This pricing model is designed for <strong>smaller independent entities</strong> where:</p>
        <ul>
            <li>✅ <strong>First camera</strong> across all stores is charged at full price</li>
            <li>✅ <strong>Additional cameras (2+)</strong> across all stores are charged at a discounted rate</li>
            <li>✅ Pricing is set at the <strong>Legal Entity level</strong>, not per store</li>
            <li>✅ Pricing changes over time (inflation adjustments)</li>
        </ul>
        <p><strong>Example:</strong> If an entity has 3 cameras total across all their stores:</p>
        <ul>
            <li>Camera 1: £3,790/year (first camera rate)</li>
            <li>Camera 2: £2,999/year (additional rate)</li>
            <li>Camera 3: £2,999/year (additional rate)</li>
            <li><strong>Total: £9,788/year</strong></li>
        </ul>
    </div>

    <div class="card">
        <h2><?= $editPricing ? '✏️ Edit' : '➕ Add New' ?> Pricing</h2>
        <form method="POST" style="max-width: 800px;">
            <input type="hidden" name="action" value="save_pricing">
            <?php if ($editPricing): ?>
                <input type="hidden" name="id" value="<?= $editPricing['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="effective_date">Effective Date *</label>
                <input type="date" id="effective_date" name="effective_date"
                       value="<?= $editPricing['effective_date'] ?? '' ?>" required>
                <small>Date when this pricing becomes active (e.g., 2027-01-01)</small>
            </div>

            <h3>First Camera Pricing</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="first_camera_price_per_annum">Annual Price *</label>
                    <input type="number" step="0.01" id="first_camera_price_per_annum" name="first_camera_price_per_annum"
                           value="<?= $editPricing['first_camera_price_per_annum'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="first_camera_price_per_quarter">Quarterly Price *</label>
                    <input type="number" step="0.01" id="first_camera_price_per_quarter" name="first_camera_price_per_quarter"
                           value="<?= $editPricing['first_camera_price_per_quarter'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="first_camera_price_per_month">Monthly Price *</label>
                    <input type="number" step="0.01" id="first_camera_price_per_month" name="first_camera_price_per_month"
                           value="<?= $editPricing['first_camera_price_per_month'] ?? '' ?>" required>
                </div>
            </div>

            <h3>Additional Cameras Pricing (Cameras 2+)</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="additional_camera_price_per_annum">Annual Price *</label>
                    <input type="number" step="0.01" id="additional_camera_price_per_annum" name="additional_camera_price_per_annum"
                           value="<?= $editPricing['additional_camera_price_per_annum'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="additional_camera_price_per_quarter">Quarterly Price *</label>
                    <input type="number" step="0.01" id="additional_camera_price_per_quarter" name="additional_camera_price_per_quarter"
                           value="<?= $editPricing['additional_camera_price_per_quarter'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="additional_camera_price_per_month">Monthly Price *</label>
                    <input type="number" step="0.01" id="additional_camera_price_per_month" name="additional_camera_price_per_month"
                           value="<?= $editPricing['additional_camera_price_per_month'] ?? '' ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3"><?= htmlspecialchars($editPricing['notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-success">
                <?= $editPricing ? '💾 Update' : '➕ Add' ?> Pricing
            </button>
            <?php if ($editPricing): ?>
                <a href="?page=admin&action=independent_pricing" class="btn">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h2>📊 Pricing History</h2>
        <?php if (empty($pricingHistory)): ?>
            <p>No pricing history found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Effective Date</th>
                        <th>First Camera (Annual)</th>
                        <th>Additional (Annual)</th>
                        <th>First Camera (Quarterly)</th>
                        <th>Additional (Quarterly)</th>
                        <th>First Camera (Monthly)</th>
                        <th>Additional (Monthly)</th>
                        <th>Notes</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pricingHistory as $pricing): ?>
                    <tr>
                        <td><strong><?= date('d M Y', strtotime($pricing['effective_date'])) ?></strong></td>
                        <td>£<?= number_format($pricing['first_camera_price_per_annum'], 2) ?></td>
                        <td>£<?= number_format($pricing['additional_camera_price_per_annum'], 2) ?></td>
                        <td>£<?= number_format($pricing['first_camera_price_per_quarter'], 2) ?></td>
                        <td>£<?= number_format($pricing['additional_camera_price_per_quarter'], 2) ?></td>
                        <td>£<?= number_format($pricing['first_camera_price_per_month'], 2) ?></td>
                        <td>£<?= number_format($pricing['additional_camera_price_per_month'], 2) ?></td>
                        <td><?= htmlspecialchars($pricing['notes'] ?? '') ?></td>
                        <td>
                            <a href="?page=admin&action=independent_pricing&edit_id=<?= $pricing['id'] ?>"
                               class="btn btn-sm">✏️ Edit</a>
                            <form method="POST" style="display: inline;"
                                  onsubmit="return confirm('Are you sure you want to delete this pricing?');">
                                <input type="hidden" name="action" value="delete_pricing">
                                <input type="hidden" name="id" value="<?= $pricing['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

