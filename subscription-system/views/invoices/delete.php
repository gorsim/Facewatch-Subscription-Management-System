<?php
/**
 * Delete Invoice
 */

use App\Models\Invoice;
use App\Database;

$invoiceId = $_GET['id'] ?? null;
$db = Database::getInstance();

if (!$invoiceId) {
    header('Location: ?page=invoices');
    exit;
}

// Get invoice details
$invoice = $db->fetchOne(
    "SELECT i.*, le.legal_entity_name 
     FROM invoices i
     JOIN legal_entities le ON i.legal_entity_id = le.id
     WHERE i.id = :id",
    ['id' => $invoiceId]
);

if (!$invoice) {
    $_SESSION['error'] = 'Invoice not found';
    header('Location: ?page=invoices');
    exit;
}

// Count how many forecast invoices will also be deleted
$forecastCount = $db->fetchOne(
    "SELECT COUNT(*) as count
     FROM invoices
     WHERE legal_entity_id = :legal_entity_id
     AND payment_frequency = :payment_frequency
     AND invoice_status = 'forecast'
     AND id != :invoice_id",
    [
        'legal_entity_id' => $invoice['legal_entity_id'],
        'payment_frequency' => $invoice['payment_frequency'],
        'invoice_id' => $invoiceId
    ]
)['count'] ?? 0;

// If confirmed, delete the invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Step 1: Find all forecast invoices that were generated from this invoice
        // (They have the same legal_entity_id and payment_frequency, and are forecast status)
        $forecastInvoices = $db->fetchAll(
            "SELECT id, invoice_number
             FROM invoices
             WHERE legal_entity_id = :legal_entity_id
             AND payment_frequency = :payment_frequency
             AND invoice_status = 'forecast'
             AND id != :invoice_id",
            [
                'legal_entity_id' => $invoice['legal_entity_id'],
                'payment_frequency' => $invoice['payment_frequency'],
                'invoice_id' => $invoiceId
            ]
        );

        $forecastCount = count($forecastInvoices);

        // Step 2: Delete camera allocations for all forecast invoices
        if ($forecastCount > 0) {
            $forecastIds = array_column($forecastInvoices, 'id');
            $placeholders = implode(',', array_fill(0, count($forecastIds), '?'));
            $db->query(
                "DELETE FROM invoice_camera_allocations WHERE invoice_id IN ($placeholders)",
                $forecastIds
            );

            // Step 3: Delete all forecast invoices
            $db->query(
                "DELETE FROM invoices WHERE id IN ($placeholders)",
                $forecastIds
            );
        }

        // Step 4: Delete camera allocations for the main invoice
        $db->query(
            "DELETE FROM invoice_camera_allocations WHERE invoice_id = :invoice_id",
            ['invoice_id' => $invoiceId]
        );

        // Step 5: Delete the main invoice itself
        $db->delete('invoices', 'id = :id', ['id' => $invoiceId]);

        $message = 'Invoice ' . htmlspecialchars($invoice['invoice_number']) . ' deleted successfully';
        if ($forecastCount > 0) {
            $message .= ' (plus ' . $forecastCount . ' forecast invoices)';
        }
        $message .= ' - cameras freed';

        $_SESSION['success'] = $message;
        header('Location: ?page=invoices');
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to delete invoice: ' . $e->getMessage();
        header('Location: ?page=invoices');
        exit;
    }
}

$pageTitle = 'Delete Invoice';
$page = 'invoices';
require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div class="card" style="border: 3px solid #dc3545; background-color: #fff3cd;">
        <h1 style="color: #dc3545;">⚠️ Delete Invoice</h1>
        
        <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <p style="margin: 0; font-size: 18px; font-weight: bold; color: #721c24;">
                ⚠️ Are you sure you want to delete this invoice?
            </p>
        </div>
        
        <h3>Invoice Details:</h3>
        <table style="width: 100%; margin-bottom: 20px;">
            <tr>
                <td style="width: 200px; font-weight: bold;">Invoice Number:</td>
                <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Legal Entity:</td>
                <td><?= htmlspecialchars($invoice['legal_entity_name']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Invoice Date:</td>
                <td><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Amount:</td>
                <td>£<?= number_format($invoice['invoice_amount'], 2) ?></td>
            </tr>
        </table>
        
        <div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <p style="margin: 0; font-weight: bold;">
                ⚠️ This action cannot be undone. The invoice will be permanently deleted from the system.
            </p>
            <?php if ($forecastCount > 0): ?>
            <p style="margin: 10px 0 0 0; font-weight: bold; color: #856404;">
                📅 This will also delete <?= $forecastCount ?> related forecast invoice<?= $forecastCount > 1 ? 's' : '' ?>.
            </p>
            <?php endif; ?>
        </div>
        
        <form method="POST" style="display: inline;">
            <input type="hidden" name="confirm_delete" value="1">
            <button type="submit" class="btn btn-danger" style="font-weight: bold;">
                🗑️ Yes, Delete This Invoice
            </button>
            <a href="?page=invoices" class="btn">← Cancel and Go Back</a>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

