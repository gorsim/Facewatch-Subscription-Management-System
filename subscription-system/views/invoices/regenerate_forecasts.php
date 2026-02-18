<?php
/**
 * Regenerate Forecast Invoices
 * Deletes existing forecasts and regenerates them with updated logic
 */

use App\Services\InvoiceAutoGenerationService;
use App\Database;

$pageTitle = 'Regenerate Forecast Invoices';
$page = 'invoices';

$db = Database::getInstance();
$autoGenService = new InvoiceAutoGenerationService();

// Get invoice ID from URL
$invoiceId = $_GET['id'] ?? null;

if (!$invoiceId) {
    $_SESSION['error'] = 'Invoice ID required';
    header('Location: ?page=invoices');
    exit;
}

// Get invoice details
$invoice = $db->fetchOne("
    SELECT i.*, le.payment_frequency, le.legal_entity_name
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE i.id = :id
", ['id' => $invoiceId]);

if (!$invoice) {
    $_SESSION['error'] = 'Invoice not found';
    header('Location: ?page=invoices');
    exit;
}

// Handle regeneration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerate'])) {
    try {
        // Delete existing forecast invoices
        $deleted = $db->execute("
            DELETE FROM invoices 
            WHERE parent_invoice_id = :parent_id 
            AND is_forecast = 1
        ", ['parent_id' => $invoiceId]);
        
        // Regenerate forecasts
        $forecastIds = $autoGenService->generateForecastInvoices($invoiceId);
        
        $_SESSION['success'] = "Successfully regenerated " . count($forecastIds) . " forecast invoices!";
        header('Location: ?page=invoices&action=view&id=' . $invoiceId);
        exit;
        
    } catch (Exception $e) {
        $error = 'Failed to regenerate forecasts: ' . $e->getMessage();
    }
}

// Count existing forecasts
$existingForecasts = $db->fetchOne("
    SELECT COUNT(*) as count
    FROM invoices
    WHERE parent_invoice_id = :parent_id
    AND is_forecast = 1
", ['parent_id' => $invoiceId]);

$forecastCount = $existingForecasts['count'] ?? 0;

require_once __DIR__ . '/../layout/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4>Regenerate Forecast Invoices</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <h5>Invoice Details</h5>
                    <table class="table table-sm">
                        <tr>
                            <th>Invoice Number:</th>
                            <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                        </tr>
                        <tr>
                            <th>Legal Entity:</th>
                            <td><?= htmlspecialchars($invoice['legal_entity_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Invoice Date:</th>
                            <td><?= htmlspecialchars($invoice['invoice_date']) ?></td>
                        </tr>
                        <tr>
                            <th>Payment Frequency:</th>
                            <td><strong><?= htmlspecialchars(ucfirst($invoice['payment_frequency'])) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Current Forecasts:</th>
                            <td><?= $forecastCount ?> forecast invoices</td>
                        </tr>
                    </table>
                    
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This will delete all existing forecast invoices for this invoice
                        and regenerate them using the current payment frequency (<?= htmlspecialchars($invoice['payment_frequency']) ?>).
                    </div>
                    
                    <form method="POST">
                        <div class="d-flex justify-content-between">
                            <a href="?page=invoices&action=view&id=<?= $invoiceId ?>" class="btn btn-secondary">
                                Cancel
                            </a>
                            <button type="submit" name="regenerate" class="btn btn-primary">
                                Regenerate Forecasts
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

