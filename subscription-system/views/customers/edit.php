<?php
/**
 * Edit Legal Entity
 */

use App\Models\LegalEntity;
use App\Models\Store;
use App\Database;

$pageTitle = 'Edit Legal Entity';
$page = 'subscribers';

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

// Get current contract
$contract = $db->fetchOne(
    "SELECT * FROM legal_entity_contracts WHERE legal_entity_id = :id ORDER BY effective_date DESC LIMIT 1",
    ['id' => $legalEntityId]
);

// Get stores for this legal entity
$storeModel = new Store();
$stores = $storeModel->getByLegalEntity($legalEntityId);

// Initialize session value ONLY if it doesn't exist yet
// This preserves the selection when navigating to/from pricing pages
if (!isset($_SESSION['edit_pricing_type_' . $legalEntityId])) {
    $_SESSION['edit_pricing_type_' . $legalEntityId] = $legalEntity['pricing_type'] ?? 'default';
    error_log("Initializing session pricing_type for entity $legalEntityId to: " . $_SESSION['edit_pricing_type_' . $legalEntityId]);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();

    try {
        // Update legal entity
        $db->update('legal_entities', [
            'legal_entity_id' => $_POST['legal_entity_id'],
            'legal_entity_name' => $_POST['legal_entity_name'],
            'xero_company_name' => $_POST['xero_company_name'] ?: null,
            'installation_date' => $_POST['installation_date'] ?: null,
            'termination_date' => $_POST['termination_date'] ?: null,
            'category' => $_POST['category'] ?: null,
            'sales_credit' => $_POST['sales_credit'] ?: null,
            'pricing_type' => $_POST['pricing_type'] ?? 'default',
            'pricing_model' => $_POST['pricing_model'] ?? 'volume_based',
            'payment_frequency' => $_POST['payment_frequency'] ?? 'annual',
            'payment_terms_days' => $_POST['payment_terms_days'] ?? 30,
        ], 'id = :id', ['id' => $legalEntityId]);

        $db->commit();

        // Clear the session pricing_type now that it's been saved to the database
        unset($_SESSION['edit_pricing_type_' . $legalEntityId]);

        $_SESSION['success'] = 'Legal Entity updated successfully';
        header('Location: ?page=subscribers&action=view&id=' . $legalEntityId);
        exit;

    } catch (Exception $e) {
        $db->rollback();
        $error = 'Failed to update legal entity: ' . $e->getMessage();
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Edit Legal Entity</h1>
        <a href="?page=subscribers&action=view&id=<?= $legalEntity['id'] ?>" class="btn" onclick="return confirmCancel()">← Cancel</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <h3>Legal Entity Information</h3>

            <div class="form-group">
                <label for="legal_entity_id">Legal Entity ID *</label>
                <input type="text" id="legal_entity_id" name="legal_entity_id"
                       value="<?= htmlspecialchars($legalEntity['legal_entity_id']) ?>" required>
            </div>

            <div class="form-group">
                <label for="legal_entity_name">Legal Entity Name *</label>
                <input type="text" id="legal_entity_name" name="legal_entity_name"
                       value="<?= htmlspecialchars($legalEntity['legal_entity_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="xero_company_name">Xero Company Name</label>
                <input type="text" id="xero_company_name" name="xero_company_name"
                       value="<?= htmlspecialchars($legalEntity['xero_company_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="installation_date">Installation Date</label>
                <input type="date" id="installation_date" name="installation_date"
                       value="<?= $legalEntity['installation_date'] ?? '' ?>">
            </div>

            <div class="form-group">
                <label for="termination_date">Termination Date</label>
                <input type="date" id="termination_date" name="termination_date"
                       value="<?= $legalEntity['termination_date'] ?? '' ?>">
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category"
                       value="<?= htmlspecialchars($legalEntity['category'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="sales_credit">Sales Credit</label>
                <input type="text" id="sales_credit" name="sales_credit"
                       value="<?= htmlspecialchars($legalEntity['sales_credit'] ?? '') ?>">
            </div>

            <h3 style="margin-top: 30px;">📊 Pricing Configuration</h3>

            <div class="form-group">
                <label for="payment_frequency">Payment Frequency *</label>
                <select id="payment_frequency" name="payment_frequency" required>
                    <option value="monthly" <?= ($legalEntity['payment_frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="quarterly" <?= ($legalEntity['payment_frequency'] ?? '') === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                    <option value="annual" <?= ($legalEntity['payment_frequency'] ?? '') === 'annual' ? 'selected' : '' ?>>Annual</option>
                </select>
            </div>

            <div class="form-group">
                <label for="payment_terms_days">Payment Terms (Days) *</label>
                <input type="number" id="payment_terms_days" name="payment_terms_days"
                       value="<?= $legalEntity['payment_terms_days'] ?? 30 ?>"
                       min="0" max="365" required>
                <small style="display: block; margin-top: 5px; color: #666;">
                    Number of days until payment is expected (used for cash flow forecasting). Default: 30 days.
                </small>
            </div>

            <?php
            // Use session value if available (preserves selection when returning from pricing page)
            // Otherwise use the database value
            $currentPricingType = $_SESSION['edit_pricing_type_' . $legalEntityId] ?? ($legalEntity['pricing_type'] ?? 'default');
            error_log("Edit page - Legal Entity ID: $legalEntityId, Session value: " . ($_SESSION['edit_pricing_type_' . $legalEntityId] ?? 'NOT SET') . ", DB value: " . ($legalEntity['pricing_type'] ?? 'NOT SET') . ", Using: $currentPricingType");
            ?>
            <div class="form-group">
                <label for="pricing_type">Pricing Type *</label>
                <select id="pricing_type" name="pricing_type" required onchange="togglePricingInfo()">
                    <option value="default" <?= $currentPricingType === 'default' ? 'selected' : '' ?>>📊 Use Default Pricing</option>
                    <option value="custom" <?= $currentPricingType === 'custom' ? 'selected' : '' ?>>🎯 Use Custom Pricing (Entity-specific rates)</option>
                </select>
                <small style="display: block; margin-top: 5px; color: #666;">
                    <span id="pricing-info-default" style="<?= $currentPricingType === 'default' ? '' : 'display:none;' ?>">
                        This entity will use the standard default pricing. Choose the pricing model below.
                    </span>
                    <span id="pricing-info-custom" style="<?= $currentPricingType === 'custom' ? '' : 'display:none;' ?>">
                        This entity has custom pricing rates. <a href="?page=admin&action=entity_pricing&id=<?= $legalEntityId ?>" onclick="savePricingType()">Manage Custom Pricing →</a>
                    </span>
                </small>
            </div>

            <div class="form-group" id="pricing-model-group" style="<?= ($legalEntity['pricing_type'] ?? 'default') === 'custom' ? 'display:none;' : '' ?>">
                <label for="pricing_model">Pricing Model *</label>
                <select id="pricing_model" name="pricing_model" required>
                    <option value="volume_based" <?= ($legalEntity['pricing_model'] ?? 'volume_based') === 'volume_based' ? 'selected' : '' ?>>📊 Volume-Based (All cameras same rate by tier)</option>
                    <option value="first_plus_additional" <?= ($legalEntity['pricing_model'] ?? 'volume_based') === 'first_plus_additional' ? 'selected' : '' ?>>📸 Independent (First camera + additional)</option>
                </select>
                <small style="display: block; margin-top: 5px; color: #666;">
                    <strong>Volume-Based:</strong> All cameras charged at the same rate based on total count (1-49, 50-149, etc.)<br>
                    <strong>Independent:</strong> First camera full price, additional cameras discounted (for smaller entities)
                </small>
            </div>

            <script>
            // Track if form has been modified
            var formModified = false;
            var allowNavigation = false;

            // Mark form as modified when any input changes
            document.addEventListener('DOMContentLoaded', function() {
                var form = document.querySelector('form');
                var inputs = form.querySelectorAll('input, select, textarea');

                inputs.forEach(function(input) {
                    input.addEventListener('change', function() {
                        formModified = true;
                    });
                });

                // Don't warn when submitting the form
                form.addEventListener('submit', function() {
                    allowNavigation = true;
                });
            });

            // Warn before leaving if form has been modified
            window.addEventListener('beforeunload', function(e) {
                if (formModified && !allowNavigation) {
                    e.preventDefault();
                    e.returnValue = ''; // Chrome requires returnValue to be set
                    return ''; // Some browsers show this message
                }
            });

            function togglePricingInfo() {
                var pricingType = document.getElementById('pricing_type').value;
                document.getElementById('pricing-info-default').style.display = pricingType === 'default' ? '' : 'none';
                document.getElementById('pricing-info-custom').style.display = pricingType === 'custom' ? '' : 'none';
                document.getElementById('pricing-model-group').style.display = pricingType === 'custom' ? 'none' : '';

                // Save the current selection to session via AJAX
                savePricingTypeToSession();
            }

            function savePricingType() {
                // Called when clicking "Manage Custom Pricing" link
                // Allow navigation to pricing page without warning
                allowNavigation = true;
                savePricingTypeToSession();
            }

            function savePricingTypeToSession() {
                var pricingType = document.getElementById('pricing_type').value;
                console.log('Saving pricing type to session:', pricingType);
                // Use fetch to save to session without page reload
                fetch('?page=subscribers&action=save_pricing_type_session', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'legal_entity_id=<?= $legalEntityId ?>&pricing_type=' + pricingType
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Session save response:', data);
                })
                .catch(error => {
                    console.error('Error saving to session:', error);
                });
            }

            function confirmCancel() {
                if (formModified) {
                    return confirm('You have unsaved changes. Are you sure you want to leave without saving?');
                }
                return true;
            }
            </script>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <a href="?page=subscribers&action=view&id=<?= $legalEntity['id'] ?>" class="btn" onclick="return confirmCancel()">Cancel</a>
            </div>
        </form>
    </div>

    <!-- Stores Section -->
    <?php if (!empty($stores)): ?>
    <div class="card" style="margin-top: 20px;">
        <h3>Stores (<?= count($stores) ?>)</h3>
        <p>Manage stores belonging to this legal entity.</p>

        <table>
            <thead>
                <tr>
                    <th>Store ID</th>
                    <th>Store Name</th>
                    <th>Installation Date</th>
                    <th>Active Cameras</th>
                    <th>Category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stores as $store): ?>
                <tr>
                    <td><?= htmlspecialchars($store['store_id']) ?></td>
                    <td><?= htmlspecialchars($store['store_name']) ?></td>
                    <td><?= $store['installation_date'] ? date('d/m/Y', strtotime($store['installation_date'])) : 'N/A' ?></td>
                    <td><?= number_format($store['active_cameras'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($store['category'] ?? 'N/A') ?></td>
                    <td>
                        <a href="?page=stores&action=view&id=<?= $store['id'] ?>" class="btn btn-sm">View</a>
                        <a href="?page=stores&action=edit&id=<?= $store['id'] ?>" class="btn btn-sm">Edit</a>
                        <a href="?page=stores&action=delete&id=<?= $store['id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure you want to delete this store?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            <a href="?page=stores&action=new&legal_entity_id=<?= $legalEntity['id'] ?>" class="btn btn-success">+ Add New Store</a>
        </div>
    </div>
    <?php else: ?>
    <div class="card" style="margin-top: 20px;">
        <h3>Stores</h3>
        <p>No stores found for this legal entity.</p>
        <a href="?page=stores&action=new&legal_entity_id=<?= $legalEntity['id'] ?>" class="btn btn-success">+ Add First Store</a>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

