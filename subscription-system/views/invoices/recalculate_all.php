<?php
/**
 * Recalculate All Invoices Action
 * Updates all invoice amounts and generates missing forecasts
 */

use App\Database;
use App\Services\InvoiceReconciliationService;
use App\Services\InvoiceAutoGenerationService;

$db = Database::getInstance();
$reconciliationService = new InvoiceReconciliationService();
$autoGenService = new InvoiceAutoGenerationService();

// Step 1: Recalculate all invoice amounts
$results = $reconciliationService->reconcileAll();

// Step 2: Generate missing forecast invoices
$invoices = $db->fetchAll("
    SELECT id, invoice_number
    FROM invoices
    WHERE (is_forecast = 0 OR is_forecast IS NULL)
    ORDER BY invoice_date DESC
");

$totalGenerated = 0;
foreach ($invoices as $invoice) {
    // Check if forecasts already exist
    $existingForecasts = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM invoices
        WHERE parent_invoice_id = :parent_id
        AND is_forecast = 1
    ", ['parent_id' => $invoice['id']]);

    if ($existingForecasts['count'] == 0) {
        try {
            // Generate forecasts for this invoice
            error_log("Generating forecasts for invoice {$invoice['invoice_number']} (ID: {$invoice['id']})");
            $forecastIds = $autoGenService->generateForecastInvoices($invoice['id']);
            $totalGenerated += count($forecastIds);
            error_log("Generated " . count($forecastIds) . " forecasts for invoice {$invoice['invoice_number']}");
        } catch (Exception $e) {
            // Log error but continue with other invoices
            error_log("Failed to generate forecasts for invoice {$invoice['invoice_number']}: " . $e->getMessage());
        }
    } else {
        error_log("Skipping invoice {$invoice['invoice_number']} - already has {$existingForecasts['count']} forecasts");
    }
}

$message = "Recalculated all invoice amounts! Updated {$results['total']} invoices.";
if ($totalGenerated > 0) {
    $message .= " Generated {$totalGenerated} missing forecast invoices.";
}
$_SESSION['success'] = $message;

// Set a flag to indicate we just recalculated (bypass checks for 5 seconds)
$_SESSION['just_recalculated'] = time();

header('Location: ?page=invoices');
exit;

