<?php
/**
 * Create Invoice from Review Page
 * Creates invoice with manually adjusted camera prices
 */

use App\Database;
use App\Services\InvoiceAutoGenerationService;

$db = Database::getInstance();

// Get form data
$cameraIds = $_POST['camera_ids'] ?? [];
$cameraPrices = $_POST['camera_prices'] ?? [];
$pricingTiers = $_POST['pricing_tiers'] ?? [];
$invoiceDate = $_POST['invoice_date'] ?? null;
$legalEntityId = $_POST['legal_entity_id'] ?? null;
$targetAmount = $_POST['target_amount'] ?? null;

error_log("create_from_review.php - Full POST data: " . print_r($_POST, true));
error_log("create_from_review.php - Camera IDs count: " . count($cameraIds));
error_log("create_from_review.php - Camera Prices count: " . count($cameraPrices));
error_log("create_from_review.php - Pricing Tiers count: " . count($pricingTiers));
error_log("create_from_review.php - Invoice Date: " . $invoiceDate);
error_log("create_from_review.php - Legal Entity ID: " . $legalEntityId);

// Validate input
if (empty($cameraIds) || empty($cameraPrices)) {
    error_log("create_from_review.php - Validation failed: No cameras or prices");
    $_SESSION['error'] = 'No cameras or prices provided';
    header('Location: ?page=invoices&action=generator');
    exit;
}

if (!$invoiceDate || !$legalEntityId) {
    error_log("create_from_review.php - Validation failed: Missing date or entity");
    $_SESSION['error'] = 'Invoice date and legal entity are required';
    header('Location: ?page=invoices&action=generator');
    exit;
}

if (count($cameraIds) !== count($cameraPrices) || count($cameraIds) !== count($pricingTiers)) {
    error_log("create_from_review.php - Validation failed: Array count mismatch - IDs: " . count($cameraIds) . ", Prices: " . count($cameraPrices) . ", Tiers: " . count($pricingTiers));
    $_SESSION['error'] = 'Mismatch between cameras, prices, and tiers. Please try again.';
    header('Location: ?page=invoices&action=generator');
    exit;
}

try {
    // Get legal entity details
    $legalEntity = $db->fetchOne(
        "SELECT * FROM legal_entities WHERE id = :id",
        ['id' => $legalEntityId]
    );

    if (!$legalEntity) {
        throw new Exception('Legal entity not found');
    }

    // Calculate total invoice amount from adjusted prices
    $invoiceAmount = 0;
    foreach ($cameraPrices as $price) {
        $invoiceAmount += floatval($price);
    }
    $invoiceAmount = round($invoiceAmount, 2);

    error_log("create_from_review.php - Total invoice amount: £$invoiceAmount");

    // Generate invoice number
    $lastInvoice = $db->fetchOne(
        "SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1"
    );

    if ($lastInvoice && preg_match('/INV-(\d+)/', $lastInvoice['invoice_number'], $matches)) {
        $nextNumber = intval($matches[1]) + 1;
    } else {
        $nextNumber = 1;
    }
    $invoiceNumber = 'INV-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

    // Determine payment frequency and invoice period
    $paymentFrequency = $legalEntity['payment_frequency'];
    $invoicePeriodMonths = match($paymentFrequency) {
        'monthly' => 1,
        'quarterly' => 3,
        'annually' => 12,
        default => 1
    };

    // Calculate due date based on payment terms
    $paymentTerms = $db->fetchOne(
        "SELECT payment_terms_days FROM legal_entities WHERE id = :id",
        ['id' => $legalEntityId]
    );
    $dueDate = date('Y-m-d', strtotime($invoiceDate . ' + ' . ($paymentTerms['payment_terms_days'] ?? 30) . ' days'));

    // Begin transaction
    $db->beginTransaction();

    try {
        // Create invoice
        $invoiceId = $db->insert('invoices', [
            'invoice_number' => $invoiceNumber,
            'legal_entity_id' => $legalEntityId,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'invoice_amount' => $invoiceAmount,
            'invoice_status' => 'draft',
            'payment_frequency' => $paymentFrequency,
            'is_auto_generated' => 0
        ]);

        error_log("Created invoice $invoiceNumber (ID: $invoiceId) with amount £$invoiceAmount");

        // Allocate cameras to invoice with adjusted prices
        foreach ($cameraIds as $index => $cameraId) {
            $price = floatval($cameraPrices[$index]);
            $tier = $pricingTiers[$index];

            // Get camera details
            $camera = $db->fetchOne(
                "SELECT ci.*
                 FROM camera_installations ci
                 WHERE ci.id = :id",
                ['id' => $cameraId]
            );

            if (!$camera) {
                throw new Exception("Camera $cameraId not found");
            }

            error_log("Camera data for ID $cameraId: store_id=" . ($camera['store_id'] ?? 'NULL') . ", camera_type=" . ($camera['camera_type'] ?? 'NULL'));

            $db->insert('invoice_camera_allocations', [
                'invoice_id' => $invoiceId,
                'camera_installation_id' => $cameraId,
                'store_id' => $camera['store_id'],
                'legal_entity_id' => $legalEntityId,
                'camera_type' => $camera['camera_type'],
                'allocated_date' => $invoiceDate,
                'price_charged' => round($price, 2),
                'pricing_tier' => $tier
            ]);

            error_log("Allocated camera $cameraId to invoice - Tier: $tier, Price: £" . round($price, 2));
        }

        $db->commit();
        error_log("Transaction committed successfully");

        $_SESSION['success'] = "Invoice $invoiceNumber created successfully with " . count($cameraIds) . " camera" . (count($cameraIds) > 1 ? 's' : '');

        // Generate forecast invoices through to 31/3/31
        try {
            $autoGenService = new InvoiceAutoGenerationService();
            $forecastIds = $autoGenService->generateForecastInvoices($invoiceId);
            if (!empty($forecastIds)) {
                $_SESSION['success'] .= " Generated " . count($forecastIds) . " forecast invoices through to 31/3/31.";
            }
        } catch (Exception $e) {
            error_log("Failed to generate forecast invoices: " . $e->getMessage());
        }

        header('Location: ?page=invoices&action=view&id=' . $invoiceId);
        exit;

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error creating invoice: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['error'] = 'Failed to create invoice: ' . $e->getMessage();
    header('Location: ?page=invoices&action=generator');
    exit;
}

