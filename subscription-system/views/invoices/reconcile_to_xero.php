<?php
/**
 * Xero Reconciliation Handler
 * Reconciles an invoice to Xero
 */

use App\Database;
use App\Services\InvoiceStatusService;

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ?page=login');
    exit;
}

$invoiceId = $_GET['id'] ?? null;
$xeroInvoiceId = $_GET['xero_id'] ?? null;
$xeroInvoiceNumber = $_GET['xero_number'] ?? null;

if (!$invoiceId || !$xeroInvoiceId || !$xeroInvoiceNumber) {
    $_SESSION['error'] = 'Missing required information for Xero reconciliation';
    header('Location: ?page=invoices&action=view&id=' . $invoiceId);
    exit;
}

$db = Database::getInstance();
$statusService = new InvoiceStatusService();

try {
    // Get current invoice
    $invoice = $db->fetchOne(
        "SELECT * FROM invoices WHERE id = :id",
        ['id' => $invoiceId]
    );
    
    if (!$invoice) {
        $_SESSION['error'] = 'Invoice not found';
        header('Location: ?page=invoices');
        exit;
    }
    
    // Check if invoice is in correct status
    if ($invoice['invoice_status'] !== 'issued') {
        $_SESSION['error'] = 'Invoice must be in "Issued" status to reconcile to Xero';
        header('Location: ?page=invoices&action=view&id=' . $invoiceId);
        exit;
    }
    
    // Reconcile to Xero
    $statusService->reconcileToXero(
        $invoiceId,
        $xeroInvoiceId,
        $xeroInvoiceNumber,
        $_SESSION['user_email'] ?? 'system'
    );
    
    $_SESSION['success'] = "Invoice successfully reconciled to Xero invoice: $xeroInvoiceNumber";
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to reconcile to Xero: ' . $e->getMessage();
}

header('Location: ?page=invoices&action=view&id=' . $invoiceId);
exit;

