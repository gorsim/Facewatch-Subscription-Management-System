<?php
/**
 * Add New Subscriber
 */

use App\Models\Subscriber;
use App\Database;

$pageTitle = 'Add New Legal Entity';
$page = 'subscribers';

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();

    try {
        // Create legal entity with new pricing system
        $entityId = $db->insert('legal_entities', [
            'legal_entity_name' => $_POST['legal_entity_name'] ?: $_POST['subscriber_name'],
            'legal_entity_id' => $_POST['legal_entity_id'] ?: null,
            'xero_company_name' => $_POST['xero_company_name'] ?: null,
            'payment_frequency' => $_POST['payment_frequency'],
            'payment_terms_days' => $_POST['payment_terms_days'] ?? 30,
            'pricing_type' => $_POST['pricing_type'] ?? 'default',
            'pricing_model' => $_POST['pricing_model'] ?? 'volume_based',
            'installation_date' => $_POST['installation_date'] ?: null,
            'category' => $_POST['category'] ?: null,
            'sales_credit' => $_POST['sales_credit'] ?: null,
        ]);

        $db->commit();
        $_SESSION['success'] = 'Legal entity created successfully with ' . ($_POST['pricing_type'] === 'custom' ? 'custom' : 'default') . ' pricing';
        header('Location: ?page=subscribers&action=view&id=' . $entityId);
        exit;

    } catch (Exception $e) {
        $db->rollback();
        $error = 'Failed to create legal entity: ' . $e->getMessage();
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Add New Legal Entity</h1>
        <a href="?page=subscribers" class="btn">← Cancel</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <h3>Legal Entity Information</h3>

            <div class="form-group">
                <label for="legal_entity_name">Legal Entity Name *</label>
                <input type="text" id="legal_entity_name" name="legal_entity_name"
                       value="<?= htmlspecialchars($_POST['legal_entity_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="legal_entity_id">Legal Entity ID</label>
                <input type="text" id="legal_entity_id" name="legal_entity_id"
                       value="<?= htmlspecialchars($_POST['legal_entity_id'] ?? '') ?>">
                <small>Optional - unique identifier for this legal entity</small>
            </div>

            <div class="form-group">
                <label for="xero_company_name">Xero Company Name</label>
                <input type="text" id="xero_company_name" name="xero_company_name"
                       value="<?= htmlspecialchars($_POST['xero_company_name'] ?? '') ?>">
                <small>Optional - company name as it appears in Xero</small>
            </div>
            

            <div class="form-group">
                <label for="installation_date">Installation Date</label>
                <input type="date" id="installation_date" name="installation_date" 
                       value="<?= $_POST['installation_date'] ?? '' ?>">
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" 
                       value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="sales_credit">Sales Credit</label>
                <input type="text" id="sales_credit" name="sales_credit" 
                       value="<?= htmlspecialchars($_POST['sales_credit'] ?? '') ?>">
            </div>
            
            <h3 style="margin-top: 30px;">Pricing Configuration</h3>

            <div class="form-group">
                <label for="payment_frequency">Payment Frequency *</label>
                <select id="payment_frequency" name="payment_frequency" required>
                    <option value="annual" <?= ($_POST['payment_frequency'] ?? 'annual') === 'annual' ? 'selected' : '' ?>>Annual</option>
                    <option value="quarterly" <?= ($_POST['payment_frequency'] ?? '') === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                    <option value="monthly" <?= ($_POST['payment_frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>

            <div class="form-group">
                <label for="payment_terms_days">Payment Terms (Days) *</label>
                <input type="number" id="payment_terms_days" name="payment_terms_days"
                       value="<?= $_POST['payment_terms_days'] ?? 30 ?>"
                       min="0" max="365" required>
                <small style="display: block; margin-top: 5px; color: #666;">
                    Number of days until payment is expected (used for cash flow forecasting). Default: 30 days.
                </small>
            </div>

            <div class="form-group">
                <label for="pricing_type">Pricing Type *</label>
                <select id="pricing_type" name="pricing_type" required onchange="togglePricingModel()">
                    <option value="default" <?= ($_POST['pricing_type'] ?? 'default') === 'default' ? 'selected' : '' ?>>📊 Use Default Pricing</option>
                    <option value="custom" <?= ($_POST['pricing_type'] ?? '') === 'custom' ? 'selected' : '' ?>>🎯 Custom Pricing (Entity-specific rates)</option>
                </select>
                <small>Default pricing uses standard rates. Custom pricing allows entity-specific rates (configure after creation).</small>
            </div>

            <div class="form-group" id="pricing-model-group" style="<?= ($_POST['pricing_type'] ?? 'default') === 'custom' ? 'display:none;' : '' ?>">
                <label for="pricing_model">Pricing Model *</label>
                <select id="pricing_model" name="pricing_model" required>
                    <option value="volume_based" <?= ($_POST['pricing_model'] ?? 'volume_based') === 'volume_based' ? 'selected' : '' ?>>📊 Volume-Based (All cameras same rate by tier)</option>
                    <option value="first_plus_additional" <?= ($_POST['pricing_model'] ?? 'volume_based') === 'first_plus_additional' ? 'selected' : '' ?>>📸 Independent (First camera + additional)</option>
                </select>
                <small style="display: block; margin-top: 5px; color: #666;">
                    <strong>Volume-Based:</strong> All cameras charged at the same rate based on total count (1-49, 50-149, etc.)<br>
                    <strong>Independent:</strong> First camera full price, additional cameras discounted (for smaller entities)
                </small>
            </div>

            <script>
            function togglePricingModel() {
                var pricingType = document.getElementById('pricing_type').value;
                document.getElementById('pricing-model-group').style.display = pricingType === 'custom' ? 'none' : '';
            }
            </script>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">Create Legal Entity</button>
                <a href="?page=subscribers" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

