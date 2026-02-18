<?php
/**
 * Invoice Generator
 * Create invoices by selecting uninvoiced cameras
 */

use App\Database;

$pageTitle = 'Invoice Generator';
$page = 'invoices';

$db = Database::getInstance();

// Get cutoff date (default to last month end)
$lastMonthEnd = new DateTime('last day of last month');
$cutoffDate = $_GET['cutoff_date'] ?? $lastMonthEnd->format('Y-m-d');

// Get sort order
$sortBy = $_GET['sort'] ?? 'legal_entity';
$sortOrder = $sortBy === 'legal_entity' ? 'le.legal_entity_name, ci.installation_date' : 'ci.installation_date DESC, le.legal_entity_name';

// Get all uninvoiced cameras installed up to the cutoff date
$uninvoicedCameras = $db->fetchAll(
    "SELECT
        ci.id as camera_id,
        ci.installation_date,
        ci.camera_name,
        ci.safr_code,
        ci.camera_type,
        ci.store_id,
        s.store_name,
        le.id as legal_entity_id,
        le.legal_entity_name,
        le.payment_frequency,
        CASE
            WHEN le.payment_frequency = 'monthly' THEN 1
            WHEN le.payment_frequency = 'quarterly' THEN 3
            WHEN le.payment_frequency = 'annually' THEN 12
            ELSE 1
        END as invoice_period_months
     FROM camera_installations ci
     JOIN stores s ON ci.store_id = s.id
     JOIN legal_entities le ON s.legal_entity_id = le.id
     WHERE ci.installation_date <= :cutoff_date
     AND ci.id NOT IN (
         SELECT camera_installation_id
         FROM invoice_camera_allocations
     )
     ORDER BY $sortOrder",
    ['cutoff_date' => $cutoffDate]
);

// Group cameras by legal entity for easy selection
$camerasByEntity = [];
foreach ($uninvoicedCameras as $camera) {
    $entityId = $camera['legal_entity_id'];
    if (!isset($camerasByEntity[$entityId])) {
        $camerasByEntity[$entityId] = [
            'legal_entity_name' => $camera['legal_entity_name'],
            'payment_frequency' => $camera['payment_frequency'],
            'cameras' => []
        ];
    }
    $camerasByEntity[$entityId]['cameras'][] = $camera;
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>📋 Invoice Generator</h1>
        <div>
            <a href="?page=invoices" class="btn">← Back to Invoices</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom: 20px;">
        <h2>Filters</h2>
        <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="invoices">
            <input type="hidden" name="action" value="generator">
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Cutoff Date:</label>
                <input type="date" name="cutoff_date" value="<?= htmlspecialchars($cutoffDate) ?>" 
                       style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Sort By:</label>
                <select name="sort" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="legal_entity" <?= $sortBy === 'legal_entity' ? 'selected' : '' ?>>Legal Entity</option>
                    <option value="install_date" <?= $sortBy === 'install_date' ? 'selected' : '' ?>>Installation Date</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Apply Filters</button>
        </form>
    </div>

    <!-- Summary -->
    <div class="card" style="margin-bottom: 20px; background: #e8f5e9;">
        <h2>📊 Summary</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <div style="font-size: 0.9em; color: #666;">Total Uninvoiced Cameras</div>
                <div style="font-size: 2em; font-weight: bold; color: #27ae60;"><?= count($uninvoicedCameras) ?></div>
            </div>
            <div>
                <div style="font-size: 0.9em; color: #666;">Legal Entities</div>
                <div style="font-size: 2em; font-weight: bold; color: #3498db;"><?= count($camerasByEntity) ?></div>
            </div>
            <div>
                <div style="font-size: 0.9em; color: #666;">Cutoff Date</div>
                <div style="font-size: 1.5em; font-weight: bold; color: #2c3e50;"><?= date('d/m/Y', strtotime($cutoffDate)) ?></div>
            </div>
        </div>
    </div>

    <?php if (empty($uninvoicedCameras)): ?>
        <div class="card">
            <p style="text-align: center; color: #999; padding: 40px;">
                ✅ No uninvoiced cameras found up to <?= date('d/m/Y', strtotime($cutoffDate)) ?>
            </p>
        </div>
    <?php else: ?>
        <!-- Camera Selection Form -->
        <form method="POST" action="?page=invoices&action=review_pricing" id="generatorForm">
            <input type="hidden" name="cutoff_date" value="<?= htmlspecialchars($cutoffDate) ?>">

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2>Select Cameras for Invoice</h2>
                    <div>
                        <span id="selectedCount" style="font-weight: bold; color: #3498db;">0 cameras selected</span>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAll" title="Select all cameras">
                            </th>
                            <th>Legal Entity</th>
                            <th>Store Name</th>
                            <th>Camera Name</th>
                            <th>SAFR Code</th>
                            <th>Camera Type</th>
                            <th>Installation Date</th>
                            <th>Invoice Period</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $currentEntity = null;
                        foreach ($uninvoicedCameras as $camera):
                            $showEntityButton = $currentEntity !== $camera['legal_entity_id'];
                            $currentEntity = $camera['legal_entity_id'];
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox"
                                       name="cameras[]"
                                       value="<?= $camera['camera_id'] ?>"
                                       class="camera-checkbox"
                                       data-entity="<?= $camera['legal_entity_id'] ?>"
                                       data-legal-entity-id="<?= $camera['legal_entity_id'] ?>"
                                       data-camera-type="<?= $camera['camera_type'] ?>">
                            </td>
                            <td>
                                <?= htmlspecialchars($camera['legal_entity_name']) ?>
                                <?php if ($showEntityButton): ?>
                                    <button type="button"
                                            class="btn btn-sm btn-success select-entity-btn"
                                            data-entity="<?= $camera['legal_entity_id'] ?>"
                                            style="margin-left: 10px; font-size: 0.8em; padding: 4px 8px;">
                                        ✓ Select All
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($camera['store_name']) ?></td>
                            <td>
                                <?php if (!empty($camera['camera_name'])): ?>
                                    <?= htmlspecialchars($camera['camera_name']) ?>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($camera['safr_code'])): ?>
                                    <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;"><?= htmlspecialchars($camera['safr_code']) ?></code>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $camera['camera_type'] === 'main' ? 'badge-primary' : 'badge-secondary' ?>">
                                    <?= ucfirst($camera['camera_type']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($camera['installation_date'])) ?></td>
                            <td><?= $camera['invoice_period_months'] ?> month<?= $camera['invoice_period_months'] > 1 ? 's' : '' ?></td>
                            <td>
                                <a href="?page=stores&action=view&id=<?= $camera['store_id'] ?>"
                                   class="btn btn-sm"
                                   target="_blank">View Store</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Invoice Creation Section -->
            <div class="card" id="invoiceCreationSection" style="display: none; background: #f8f9fa;">
                <h2>Create Invoice</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Invoice Date:</label>
                        <input type="date"
                               id="invoiceDate"
                               name="invoice_date"
                               value="<?= $lastMonthEnd->format('Y-m-d') ?>"
                               required
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Calculated Amount:</label>
                        <input type="text"
                               id="calculatedAmount"
                               readonly
                               value="£0.00"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #e9ecef;">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Override Amount (optional):</label>
                        <input type="number"
                               name="override_amount"
                               step="0.01"
                               placeholder="Leave blank to use calculated"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Invoice Notes (optional):</label>
                    <textarea name="invoice_notes"
                              rows="3"
                              placeholder="Add any internal notes about this invoice..."
                              style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit;"></textarea>
                </div>

                <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                    <p style="margin: 0; font-weight: bold;">⚠️ Important:</p>
                    <ul style="margin: 10px 0 0 20px;">
                        <li>All selected cameras must belong to the <strong>same Legal Entity</strong></li>
                        <li>Invoice number will be auto-generated</li>
                        <li>Amount is calculated based on pricing table</li>
                        <li>You can override the amount if needed</li>
                        <li><strong>Next step:</strong> Review and adjust individual camera prices before creating the invoice</li>
                    </ul>
                </div>

                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn btn-success" style="font-size: 1.1em; padding: 12px 30px;">
                        📝 Review & Create Invoice →
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// Track selected cameras
let selectedCameras = new Set();

// Update selected count
function updateSelectedCount() {
    const count = selectedCameras.size;
    document.getElementById('selectedCount').textContent = count + ' camera' + (count !== 1 ? 's' : '') + ' selected';

    // Show/hide invoice creation section
    const section = document.getElementById('invoiceCreationSection');
    if (count > 0) {
        section.style.display = 'block';
        calculateAmount();
    } else {
        section.style.display = 'none';
    }
}

// Calculate invoice amount based on selected cameras
function calculateAmount() {
    // Get all selected camera checkboxes
    const selectedCheckboxes = Array.from(selectedCameras).map(id =>
        document.querySelector(`input[name="cameras[]"][value="${id}"]`)
    );

    if (selectedCheckboxes.length === 0) {
        document.getElementById('calculatedAmount').value = '£0.00';
        return;
    }

    // Get legal entity ID from first selected camera
    const firstCheckbox = selectedCheckboxes[0];
    const legalEntityId = firstCheckbox.dataset.legalEntityId;

    // Group cameras by store to count first vs additional cameras per store
    const storeGroups = {};
    selectedCheckboxes.forEach(checkbox => {
        const storeId = checkbox.closest('tr').querySelector('td:nth-child(3)').textContent.trim(); // Store name as key
        if (!storeGroups[storeId]) {
            storeGroups[storeId] = 0;
        }
        storeGroups[storeId]++;
    });

    // Count first cameras (one per store) and additional cameras (rest)
    let firstCameras = Object.keys(storeGroups).length; // One first camera per store
    let additionalCameras = 0;
    Object.values(storeGroups).forEach(count => {
        if (count > 1) {
            additionalCameras += (count - 1); // All cameras after the first in each store
        }
    });

    const totalCameras = firstCameras + additionalCameras;

    // Get the invoice date from the form
    const invoiceDate = document.getElementById('invoiceDate').value || new Date().toISOString().split('T')[0];

    // Make AJAX call to get pricing
    fetch('?page=invoices&action=calculate_pricing', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `legal_entity_id=${legalEntityId}&camera_count=${totalCameras}&first_cameras=${firstCameras}&additional_cameras=${additionalCameras}&invoice_date=${invoiceDate}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('calculatedAmount').value = '£' + parseFloat(data.amount).toFixed(2);
        } else {
            document.getElementById('calculatedAmount').value = 'Error calculating';
            console.error('Pricing calculation error:', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('calculatedAmount').value = 'Error';
    });
}

// Handle individual checkbox changes
document.querySelectorAll('.camera-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            selectedCameras.add(this.value);
        } else {
            selectedCameras.delete(this.value);
        }
        updateSelectedCount();
    });
});

// Handle "Select All" checkbox
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.camera-checkbox').forEach(checkbox => {
        checkbox.checked = this.checked;
        if (this.checked) {
            selectedCameras.add(checkbox.value);
        } else {
            selectedCameras.delete(checkbox.value);
        }
    });
    updateSelectedCount();
});

// Handle "Select All for Entity" buttons
document.querySelectorAll('.select-entity-btn').forEach(button => {
    button.addEventListener('click', function() {
        const entityId = this.dataset.entity;
        const checkboxes = document.querySelectorAll(`.camera-checkbox[data-entity="${entityId}"]`);
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);

        checkboxes.forEach(checkbox => {
            checkbox.checked = !allChecked;
            if (!allChecked) {
                selectedCameras.add(checkbox.value);
            } else {
                selectedCameras.delete(checkbox.value);
            }
        });

        this.textContent = allChecked ? '✓ Select All' : '✗ Deselect All';
        updateSelectedCount();
    });
});

// Handle invoice date changes - recalculate pricing when date changes
document.getElementById('invoiceDate')?.addEventListener('change', function() {
    // Recalculate pricing if cameras are selected
    if (selectedCameras.size > 0) {
        calculateAmount();
    }
});

// Form validation
document.getElementById('generatorForm')?.addEventListener('submit', function(e) {
    if (selectedCameras.size === 0) {
        e.preventDefault();
        alert('Please select at least one camera to create an invoice.');
        return false;
    }
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>


