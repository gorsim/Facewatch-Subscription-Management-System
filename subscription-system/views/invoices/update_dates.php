<?php
/**
 * Update Invoice Dates
 * Allows editing invoice_date and due_date for draft invoices
 */

use App\Database;

$db = Database::getInstance();
$invoiceId = $_GET['id'] ?? null;

if (!$invoiceId) {
    $_SESSION['error'] = 'Invoice ID is required';
    header('Location: ?page=invoices');
    exit;
}

// Get invoice to check if it's a draft
$invoice = $db->fetchOne(
    "SELECT id, invoice_status FROM invoices WHERE id = :id",
    ['id' => $invoiceId]
);

if (!$invoice) {
    $_SESSION['error'] = 'Invoice not found';
    header('Location: ?page=invoices');
    exit;
}

// Only allow editing draft invoices
if ($invoice['invoice_status'] !== 'draft') {
    $_SESSION['error'] = 'Only draft invoices can be edited';
    header('Location: ?page=invoices&action=view&id=' . $invoiceId);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceDate = $_POST['invoice_date'] ?? null;
    $dueDate = $_POST['due_date'] ?? null;

    if (!$invoiceDate) {
        $_SESSION['error'] = 'Invoice date is required';
        header('Location: ?page=invoices&action=view&id=' . $invoiceId);
        exit;
    }

    try {
        // Get the old invoice date before updating
        $oldInvoice = $db->fetchOne(
            "SELECT invoice_date, parent_invoice_id FROM invoices WHERE id = :id",
            ['id' => $invoiceId]
        );
        $oldDate = $oldInvoice['invoice_date'];
        $hasParent = !empty($oldInvoice['parent_invoice_id']);

        // Update the invoice dates
        $updateData = [
            'invoice_date' => $invoiceDate
        ];

        // Only update due_date if provided
        if (!empty($dueDate)) {
            $updateData['due_date'] = $dueDate;
        } else {
            // Set to NULL if empty
            $updateData['due_date'] = null;
        }

        $db->update('invoices', $updateData, 'id = :id', ['id' => $invoiceId]);

        // If the date changed AND this is an original invoice (not a converted forecast),
        // regenerate the forecast chain
        if ($oldDate !== $invoiceDate && !$hasParent) {
            require_once __DIR__ . '/../../app/Services/InvoiceAutoGenerationService.php';
            $autoGenService = new App\Services\InvoiceAutoGenerationService();

            // Delete old forecasts
            $db->query(
                "DELETE FROM invoice_camera_allocations
                 WHERE invoice_id IN (
                     SELECT id FROM invoices
                     WHERE parent_invoice_id = :parent_id
                     AND is_forecast = 1
                 )",
                ['parent_id' => $invoiceId]
            );

            $db->query(
                "DELETE FROM invoices
                 WHERE parent_invoice_id = :parent_id
                 AND is_forecast = 1",
                ['parent_id' => $invoiceId]
            );

            // Generate new forecasts with the new date
            $forecastIds = $autoGenService->generateForecastInvoices($invoiceId);

            error_log("Regenerated " . count($forecastIds) . " forecasts for invoice $invoiceId after date change from $oldDate to $invoiceDate");
            $_SESSION['success'] = 'Invoice dates updated and ' . count($forecastIds) . ' forecasts regenerated!';
        } else {
            $_SESSION['success'] = 'Invoice dates updated successfully!';
            error_log("Updated invoice $invoiceId dates - Invoice Date: $invoiceDate, Due Date: " . ($dueDate ?: 'NULL'));
        }

    } catch (Exception $e) {
        error_log("Error updating invoice dates: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update invoice dates: ' . $e->getMessage();
    }

    header('Location: ?page=invoices&action=view&id=' . $invoiceId);
    exit;
}

// If not POST, redirect back to view
header('Location: ?page=invoices&action=view&id=' . $invoiceId);
exit;

