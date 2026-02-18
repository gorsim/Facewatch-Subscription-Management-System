<?php
/**
 * Reconcile Invoice to Xero - Form Interface
 * Professional form-based reconciliation (no JavaScript prompts needed)
 */

use App\Database;
use App\Services\InvoiceStatusService;

$pageTitle = 'Reconcile to Xero';
$page = 'invoices';

$invoiceId = $_GET['id'] ?? null;

if (!$invoiceId) {
    $_SESSION['error'] = 'Invoice ID is required';
    header('Location: ?page=invoices');
    exit;
}

$db = Database::getInstance();
$statusService = new InvoiceStatusService();

// Get invoice details
$invoice = $db->fetchOne("
    SELECT i.*, le.legal_entity_name, le.xero_company_name
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE i.id = :id
", ['id' => $invoiceId]);

if (!$invoice) {
    $_SESSION['error'] = 'Invoice not found';
    header('Location: ?page=invoices');
    exit;
}

// Check if invoice can be reconciled
if ($invoice['invoice_status'] !== 'issued') {
    $_SESSION['error'] = 'Only issued invoices can be reconciled to Xero';
    header('Location: ?page=invoices&action=view&id=' . $invoiceId);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $xeroInvoiceId = trim($_POST['xero_invoice_id'] ?? '');
    $xeroInvoiceNumber = trim($_POST['xero_invoice_number'] ?? '');
    $xeroInvoiceAmount = trim($_POST['xero_invoice_amount'] ?? '');

    // Validate inputs
    $errors = [];
    if (empty($xeroInvoiceId)) {
        $errors[] = 'Xero Invoice ID is required';
    }
    if (empty($xeroInvoiceNumber)) {
        $errors[] = 'Xero Invoice Number is required';
    }
    if (empty($xeroInvoiceAmount)) {
        $errors[] = 'Xero Invoice Amount is required';
    } elseif (!is_numeric($xeroInvoiceAmount) || $xeroInvoiceAmount < 0) {
        $errors[] = 'Xero Invoice Amount must be a valid positive number';
    }

    if (empty($errors)) {
        try {
            $statusService->reconcileToXero(
                $invoiceId,
                $xeroInvoiceId,
                $xeroInvoiceNumber,
                floatval($xeroInvoiceAmount),
                $_SESSION['user_email'] ?? 'system'
            );

            $_SESSION['success'] = "Invoice successfully reconciled to Xero invoice: $xeroInvoiceNumber";
            header('Location: ?page=invoices&action=view&id=' . $invoiceId);
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = 'Failed to reconcile: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="margin-bottom: 20px;">
        <a href="?page=invoices&action=view&id=<?= $invoiceId ?>" class="btn">← Back to Invoice</a>
    </div>

    <div class="card">
        <h1>🔄 Reconcile Invoice to Xero</h1>
        
        <!-- Invoice Summary -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0;">
            <h3 style="margin-top: 0;">Invoice Details</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <strong>Invoice Number:</strong><br>
                    <span style="font-size: 1.2em; color: #333;"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
                </div>
                <div>
                    <strong>Legal Entity:</strong><br>
                    <?= htmlspecialchars($invoice['legal_entity_name']) ?>
                </div>
                <div>
                    <strong>Amount:</strong><br>
                    <span style="font-size: 1.2em; color: #5cb85c;">£<?= number_format($invoice['invoice_amount'], 2) ?></span>
                </div>
                <div>
                    <strong>Invoice Date:</strong><br>
                    <?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 4px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #0c5460;">📋 Instructions</h4>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li>Create the invoice in Xero first (if not already done)</li>
                <li>Copy the <strong>Xero Invoice ID</strong> from the Xero invoice URL</li>
                <li>Copy the <strong>Xero Invoice Number</strong> from the Xero invoice page</li>
                <li>Enter both values below and click "Reconcile"</li>
            </ol>
            <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #0c5460;">
                <strong>Note:</strong> Once reconciled, this invoice will be locked and cannot be edited or deleted.
            </p>
        </div>

        <!-- Reconciliation Form -->
        <form method="POST" style="margin-top: 20px;">
            <div style="margin-bottom: 20px;">
                <label for="xero_invoice_id" style="display: block; font-weight: bold; margin-bottom: 5px;">
                    Xero Invoice ID <span style="color: #d9534f;">*</span>
                </label>
                <input type="text" 
                       id="xero_invoice_id" 
                       name="xero_invoice_id" 
                       required
                       placeholder="e.g., a1b2c3d4-e5f6-7890-abcd-ef1234567890"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
                <small style="color: #666; display: block; margin-top: 5px;">
                    Find this in the Xero invoice URL: <code>InvoiceID=<strong>a1b2c3d4-e5f6...</strong></code>
                </small>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="xero_invoice_number" style="display: block; font-weight: bold; margin-bottom: 5px;">
                    Xero Invoice Number <span style="color: #d9534f;">*</span>
                </label>
                <input type="text"
                       id="xero_invoice_number"
                       name="xero_invoice_number"
                       required
                       placeholder="e.g., INV-2026-001"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <small style="color: #666; display: block; margin-top: 5px;">
                    The invoice number shown on the Xero invoice page
                </small>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="xero_invoice_amount" style="display: block; font-weight: bold; margin-bottom: 5px;">
                    Xero Invoice Amount <span style="color: #d9534f;">*</span>
                </label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 10px; top: 10px; font-size: 1.1em; color: #666;">£</span>
                    <input type="number"
                           id="xero_invoice_amount"
                           name="xero_invoice_amount"
                           required
                           step="0.01"
                           min="0"
                           placeholder="0.00"
                           style="width: 100%; padding: 10px 10px 10px 25px; border: 1px solid #ddd; border-radius: 4px; font-size: 1.1em;">
                </div>
                <small style="color: #666; display: block; margin-top: 5px;">
                    The total amount shown on the Xero invoice (this will be compared against your system amount of <strong>£<?= number_format($invoice['invoice_amount'], 2) ?></strong>)
                </small>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; display: flex; gap: 10px; justify-content: flex-end;">
                <a href="?page=invoices&action=view&id=<?= $invoiceId ?>" class="btn">Cancel</a>
                <button type="submit" class="btn btn-success" style="font-size: 1.1em; padding: 12px 24px;">
                    ✅ Reconcile to Xero
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

