<?php
/**
 * Invoice Status Change Handler
 * Handles status transitions for invoices
 */

use App\Database;
use App\Services\InvoiceStatusService;

// Session already started in index.php - don't call session_start() again

// DEBUG: Log that we got here
error_log("change_status.php called with ID: " . ($_GET['id'] ?? 'none') . ", Status: " . ($_GET['status'] ?? 'none'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("change_status.php: User not logged in, redirecting to login");
    $_SESSION['error'] = 'Please log in to change invoice status';
    header('Location: ?page=login');
    exit;
}

$invoiceId = $_GET['id'] ?? null;
$newStatus = $_GET['status'] ?? null;
$notes = $_GET['notes'] ?? null;

if (!$invoiceId || !$newStatus) {
    error_log("change_status.php: Missing parameters - ID: $invoiceId, Status: $newStatus");
    $_SESSION['error'] = 'Missing invoice ID or status';
    header('Location: ?page=invoices');
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
    
    // If cancelling, delete all related forecast invoices first
    if ($newStatus === 'cancelled') {
        // Find all forecast invoices for this legal entity and payment frequency
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

        if ($forecastCount > 0) {
            // Delete camera allocations for all forecast invoices
            $forecastIds = array_column($forecastInvoices, 'id');
            $placeholders = implode(',', array_fill(0, count($forecastIds), '?'));
            $db->query(
                "DELETE FROM invoice_camera_allocations WHERE invoice_id IN ($placeholders)",
                $forecastIds
            );

            // Delete all forecast invoices
            $db->query(
                "DELETE FROM invoices WHERE id IN ($placeholders)",
                $forecastIds
            );

            error_log("change_status.php: Deleted $forecastCount forecast invoices when cancelling invoice $invoiceId");
        }
    }

    // Change status
    $statusService->changeStatus(
        $invoiceId,
        $newStatus,
        $_SESSION['user_email'] ?? 'system',
        $notes
    );

    // Success message
    $statusLabels = [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'reconciled_to_xero' => 'Reconciled to Xero',
        'cancelled' => 'Cancelled',
        'merged' => 'Merged'
    ];

    $successMessage = "Invoice status changed to: {$statusLabels[$newStatus]}";
    if ($newStatus === 'cancelled' && isset($forecastCount) && $forecastCount > 0) {
        $successMessage .= " (plus $forecastCount forecast invoices deleted)";
    }
    $_SESSION['success'] = $successMessage;

} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to change status: ' . $e->getMessage();
}

// Check if headers have already been sent
if (headers_sent($file, $line)) {
    error_log("change_status.php: Headers already sent in $file on line $line");
    echo "<div style='background: #f8d7da; padding: 20px; margin: 20px; border: 2px solid #dc3545;'>";
    echo "<h2>⚠️ Redirect Failed</h2>";
    echo "<p>Headers were already sent in <strong>$file</strong> on line <strong>$line</strong></p>";
    echo "<p>Status was changed successfully, but automatic redirect failed.</p>";
    echo "<p><a href='?page=invoices&action=view&id=$invoiceId' style='color: #007bff; font-weight: bold;'>Click here to view the invoice</a></p>";
    echo "</div>";
    exit;
}

header('Location: ?page=invoices&action=view&id=' . $invoiceId);
exit;

