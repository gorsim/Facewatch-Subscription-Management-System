<?php
/**
 * Delete Xero Invoice Action
 * Handles deletion of incorrect Xero invoices from import sessions
 */

use App\Services\InvoiceMatchingService;

// Log that we got here
error_log("DELETE XERO INVOICE: Action called");
error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("GET DATA: " . print_r($_GET, true));
error_log("POST DATA: " . print_r($_POST, true));

// Get parameters from either POST or GET (to support both form and link)
$xeroInvoiceId = $_POST['xero_invoice_id'] ?? $_GET['xero_invoice_id'] ?? null;
$sessionId = $_POST['session_id'] ?? $_GET['session_id'] ?? null;

error_log("DELETE XERO INVOICE: xero_invoice_id = $xeroInvoiceId, session_id = $sessionId");

// Validate parameters
if (!$xeroInvoiceId || !$sessionId) {
    $_SESSION['error'] = 'Missing required parameters';
    error_log("DELETE XERO INVOICE: Missing parameters");
    header('Location: ?page=invoices&action=smart_match');
    exit;
}

try {
    error_log("DELETE XERO INVOICE: Attempting to delete invoice $xeroInvoiceId from session $sessionId");

    $matchingService = new InvoiceMatchingService();

    // Delete the Xero invoice
    $matchingService->deleteXeroInvoice(
        $xeroInvoiceId,
        $sessionId,
        $_SESSION['user_id']
    );

    $_SESSION['success'] = 'Xero invoice deleted successfully';
    error_log("DELETE XERO INVOICE: Success!");

} catch (Exception $e) {
    $_SESSION['error'] = 'Error deleting invoice: ' . $e->getMessage();
    error_log("DELETE XERO INVOICE: Error - " . $e->getMessage());
    error_log("DELETE XERO INVOICE: Stack trace - " . $e->getTraceAsString());
}

// Redirect back to the match results page
error_log("DELETE XERO INVOICE: Redirecting to session $sessionId");
header('Location: ?page=invoices&action=smart_match&session=' . $sessionId);
exit;

