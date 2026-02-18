<?php
/**
 * Create Invoice from Generator
 * Handles invoice creation from the Invoice Generator page
 */

use App\Database;
use App\Services\InvoiceService;
use App\Services\PricingService;
use App\Services\InvoiceAutoGenerationService;

$db = Database::getInstance();
$pricingService = new PricingService();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: ?page=invoices&action=generator');
    exit;
}

// Get form data
error_log("create_from_generator.php - POST data: " . print_r($_POST, true));
$cameraIds = $_POST['cameras'] ?? [];
$invoiceDate = $_POST['invoice_date'] ?? null;
$overrideAmount = !empty($_POST['override_amount']) ? floatval($_POST['override_amount']) : null;
error_log("create_from_generator.php - Camera IDs: " . print_r($cameraIds, true));
error_log("create_from_generator.php - Invoice Date: " . $invoiceDate);

// Validate input
if (empty($cameraIds)) {
    $_SESSION['error'] = 'No cameras selected';
    header('Location: ?page=invoices&action=generator');
    exit;
}

if (!$invoiceDate) {
    $_SESSION['error'] = 'Invoice date is required';
    header('Location: ?page=invoices&action=generator');
    exit;
}

try {
    // Get camera details and verify they all belong to the same legal entity
    $placeholders = implode(',', array_fill(0, count($cameraIds), '?'));
    $cameras = $db->fetchAll(
        "SELECT ci.*, s.store_name, le.id as legal_entity_id, le.legal_entity_name, le.payment_frequency
         FROM camera_installations ci
         JOIN stores s ON ci.store_id = s.id
         JOIN legal_entities le ON s.legal_entity_id = le.id
         WHERE ci.id IN ($placeholders)",
        $cameraIds
    );
    
    if (empty($cameras)) {
        $_SESSION['error'] = 'No valid cameras found';
        header('Location: ?page=invoices&action=generator');
        exit;
    }
    
    // Check all cameras belong to same legal entity
    $legalEntityIds = array_unique(array_column($cameras, 'legal_entity_id'));
    if (count($legalEntityIds) > 1) {
        $_SESSION['error'] = 'All cameras must belong to the same Legal Entity';
        header('Location: ?page=invoices&action=generator');
        exit;
    }
    
    $legalEntityId = $legalEntityIds[0];
    $legalEntity = $cameras[0];
    
    // Check if any cameras are already allocated
    $alreadyAllocated = $db->fetchAll(
        "SELECT camera_installation_id, invoice_id 
         FROM invoice_camera_allocations 
         WHERE camera_installation_id IN ($placeholders)",
        $cameraIds
    );
    
    if (!empty($alreadyAllocated)) {
        $allocatedIds = array_column($alreadyAllocated, 'camera_installation_id');
        $_SESSION['error'] = 'Some cameras are already allocated to invoices: ' . implode(', ', $allocatedIds);
        header('Location: ?page=invoices&action=generator');
        exit;
    }
    
    // Count cameras by type
    $mainCameras = 0;
    $additionalCameras = 0;
    foreach ($cameras as $camera) {
        if ($camera['camera_type'] === 'main') {
            $mainCameras++;
        } else {
            $additionalCameras++;
        }
    }
    $totalCameras = count($cameras);

    // Get TOTAL active cameras for this legal entity to determine pricing tier
    $totalActiveCameras = $db->fetchOne(
        "SELECT COUNT(*) as total
         FROM camera_installations ci
         JOIN stores s ON ci.store_id = s.id
         WHERE s.legal_entity_id = :legal_entity_id
         AND ci.installation_date <= :as_of_date
         AND (ci.removal_date IS NULL OR ci.removal_date > :as_of_date2)",
        [
            'legal_entity_id' => $legalEntityId,
            'as_of_date' => $invoiceDate,
            'as_of_date2' => $invoiceDate
        ]
    );

    $totalCameraCount = intval($totalActiveCameras['total'] ?? 0);

    // Calculate invoice amount if not overridden
    if ($overrideAmount !== null) {
        $invoiceAmount = $overrideAmount;
    } else {
        // Get pricing from PricingService based on TOTAL camera count (for tier)
        $pricing = $pricingService->getPricingForEntity(
            $legalEntityId,
            $totalCameraCount,
            $invoiceDate
        );

        // Calculate total amount based on pricing model
        if ($pricing['pricing_type'] === 'first_plus_additional') {
            // First camera + additional model
            $invoiceAmount = $pricing['total_cost'];
        } else {
            // Volume-based model: rate per camera * camera count
            $invoiceAmount = $pricing['rate_to_use'] * $totalCameras;
        }

        // Round to 2 decimal places
        $invoiceAmount = round($invoiceAmount, 2);
    }
    
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
        
        // Calculate price per camera for allocation
        $pricePerCamera = $invoiceAmount / $totalCameras;

        // Allocate cameras to invoice
        error_log("About to allocate " . count($cameras) . " cameras to invoice $invoiceId");
        foreach ($cameras as $camera) {
            error_log("Allocating camera " . $camera['id'] . " to invoice");
            $db->insert('invoice_camera_allocations', [
                'invoice_id' => $invoiceId,
                'camera_installation_id' => $camera['id'],
                'store_id' => $camera['store_id'],
                'legal_entity_id' => $legalEntityId,
                'camera_type' => $camera['camera_type'],
                'allocated_date' => $invoiceDate,
                'price_charged' => round($pricePerCamera, 2),
                'pricing_tier' => $pricing['tier_name'] ?? 'Standard'
            ]);
        }

        error_log("About to commit transaction");
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
            // Log error but don't fail the main invoice creation
            error_log("Failed to generate forecast invoices: " . $e->getMessage());
        }

        error_log("About to redirect to invoice view page: ?page=invoices&action=view&id=" . $invoiceId);
        header('Location: ?page=invoices&action=view&id=' . $invoiceId);
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Exception in create_from_generator: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['error'] = 'Failed to create invoice: ' . $e->getMessage();
    header('Location: ?page=invoices&action=generator');
    exit;
}

