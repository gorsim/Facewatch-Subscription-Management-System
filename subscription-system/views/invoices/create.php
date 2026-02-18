<?php
/**
 * Create New Invoice
 */

use App\Services\InvoiceGenerationService;
use App\Services\InvoiceAutoGenerationService;
use App\Database;

$pageTitle = 'Create Invoice';
$page = 'invoices';

$db = Database::getInstance();
$invoiceService = new InvoiceGenerationService();
$autoGenService = new InvoiceAutoGenerationService();

// Get legal entity ID from URL
$legalEntityId = $_GET['legal_entity_id'] ?? null;

if (!$legalEntityId) {
    $_SESSION['error'] = 'Legal entity ID required';
    header('Location: ?page=subscribers');
    exit;
}

// Handle form submission FIRST before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== CREATE INVOICE FORM SUBMITTED ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("Cameras selected: " . (empty($_POST['cameras']) ? 'NONE' : count($_POST['cameras'])));

    try {
        // Create invoice
        $invoiceId = $invoiceService->createInvoice(
            $legalEntityId,
            $_POST['invoice_date'],
            [
                'status' => 'draft',
                'created_by' => $_SESSION['user'] ?? 'admin',
                'notes' => $_POST['notes'] ?? null
            ]
        );

        // Allocate selected cameras
        if (!empty($_POST['cameras'])) {
            error_log("Calling allocateCameras with invoice ID: $invoiceId");
            error_log("Camera IDs: " . implode(', ', $_POST['cameras']));

            try {
                $result = $invoiceService->allocateCameras(
                    $invoiceId,
                    $_POST['cameras'],
                    $_SESSION['user'] ?? 'admin'
                );

                error_log("allocateCameras result: " . print_r($result, true));
                $_SESSION['success'] = "Invoice created successfully! Invoice number: {$result['invoice_id']}, Total: £" . number_format($result['total_amount'], 2);
            } catch (Exception $e) {
                error_log("allocateCameras ERROR: " . $e->getMessage());
                throw $e; // Re-throw to be caught by outer try-catch
            }
        } else {
            $_SESSION['success'] = "Invoice created successfully! No cameras allocated yet.";
        }

        // Generate forecast invoices through to 31/3/31
        try {
            $forecastIds = $autoGenService->generateForecastInvoices($invoiceId);
            if (!empty($forecastIds)) {
                $_SESSION['success'] .= " Generated " . count($forecastIds) . " forecast invoices through to 31/3/31.";
            }
        } catch (Exception $e) {
            // Log error but don't fail the main invoice creation
            error_log("Failed to generate forecast invoices: " . $e->getMessage());
        }

        header('Location: ?page=invoices&action=view&id=' . $invoiceId);
        exit;

    } catch (Exception $e) {
        $error = 'Failed to create invoice: ' . $e->getMessage();
    }
}

// Get legal entity details
$entity = $db->fetchOne(
    "SELECT * FROM legal_entities WHERE id = :id",
    ['id' => $legalEntityId]
);

if (!$entity) {
    $_SESSION['error'] = 'Legal entity not found';
    header('Location: ?page=subscribers');
    exit;
}

// Get all active cameras for this legal entity that are NOT already allocated to an invoice
$cameras = $db->fetchAll("
    SELECT
        ci.*,
        s.store_name,
        s.id as store_id
    FROM camera_installations ci
    JOIN stores s ON ci.store_id = s.id
    LEFT JOIN invoice_camera_allocations ica ON ci.id = ica.camera_installation_id
        AND ica.removed_date IS NULL
    WHERE s.legal_entity_id = :legal_entity_id
    AND ci.removal_date IS NULL
    AND ica.id IS NULL
    ORDER BY s.store_name, ci.camera_type, ci.id
", ['legal_entity_id' => $legalEntityId]);

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1>Create Invoice</h1>
            <p style="color: #666; margin-top: 5px;">
                For: <strong><?= htmlspecialchars($entity['legal_entity_name']) ?></strong>
            </p>
        </div>
        <a href="?page=subscribers&action=view&id=<?= $legalEntityId ?>" class="btn">← Back to Entity</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="?page=invoices&action=create&legal_entity_id=<?= $legalEntityId ?>">
            <h3>Invoice Details</h3>
            
            <div class="form-group">
                <label for="invoice_date">Invoice Date *</label>
                <input type="date" id="invoice_date" name="invoice_date" 
                       value="<?= $_POST['invoice_date'] ?? date('Y-m-d') ?>" required>
                <small>The date this invoice is issued</small>
            </div>
            
            <div class="form-group">
                <label>Payment Frequency</label>
                <input type="text" value="<?= ucfirst($entity['payment_frequency']) ?>" disabled>
                <small>Set at legal entity level</small>
            </div>
            
            <div class="form-group">
                <label for="notes">Internal Notes</label>
                <textarea id="notes" name="notes" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                <small>Optional notes about this invoice (not shown to customer)</small>
            </div>
            
            <h3 style="margin-top: 30px;">Select Cameras to Include</h3>
            
            <?php if (empty($cameras)): ?>
                <div class="alert alert-warning">
                    <strong>No active cameras found</strong><br>
                    This legal entity has no active cameras. You can still create the invoice and add cameras later.
                </div>
            <?php else: ?>
                <p style="color: #666; margin-bottom: 15px;">
                    Select the cameras to include on this invoice. The system will automatically calculate pricing based on the entity's pricing model.
                </p>
                
                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 15px;">
                    <table style="width: 100%;">
                        <thead style="position: sticky; top: 0; background: white;">
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" id="select_all" onclick="toggleAllCameras(this)">
                                </th>
                                <th>Store</th>
                                <th>Camera Type</th>
                                <th>Installation Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $currentStore = null;
                            foreach ($cameras as $camera): 
                                if ($currentStore !== $camera['store_name']) {
                                    $currentStore = $camera['store_name'];
                                    echo '<tr style="background-color: #f8f9fa;"><td colspan="4"><strong>' . htmlspecialchars($currentStore) . '</strong></td></tr>';
                                }
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="cameras[]" value="<?= $camera['id'] ?>"
                                           class="camera-checkbox" <?= in_array($camera['id'], $_POST['cameras'] ?? []) ? 'checked' : '' ?>>
                                </td>
                                <td><?= htmlspecialchars($camera['store_name']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($camera['camera_type'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($camera['installation_date'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <script>
                function toggleAllCameras(checkbox) {
                    const checkboxes = document.querySelectorAll('.camera-checkbox');
                    checkboxes.forEach(cb => cb.checked = checkbox.checked);
                }
                </script>
            <?php endif; ?>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                <button type="submit" class="btn btn-success">Create Invoice</button>
                <a href="?page=subscribers&action=view&id=<?= $legalEntityId ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

