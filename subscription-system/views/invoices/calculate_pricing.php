<?php
/**
 * Calculate Pricing for Invoice Generator
 * AJAX endpoint to calculate invoice amount based on selected cameras
 */

use App\Database;
use App\Services\PricingService;

header('Content-Type: application/json');

// Log the request for debugging
error_log("calculate_pricing.php called with POST data: " . print_r($_POST, true));

$db = Database::getInstance();
$pricingService = new PricingService();

// Get parameters
$legalEntityId = $_POST['legal_entity_id'] ?? null;
$cameraCount = intval($_POST['camera_count'] ?? 0);
$mainCameras = intval($_POST['main_cameras'] ?? 0);
$additionalCameras = intval($_POST['additional_cameras'] ?? 0);
$invoiceDate = $_POST['invoice_date'] ?? date('Y-m-d'); // Use provided invoice date or default to today

error_log("Parsed params - Entity: $legalEntityId, Count: $cameraCount, Main: $mainCameras, Additional: $additionalCameras, Invoice Date: $invoiceDate");

// Validate input
if (!$legalEntityId || $cameraCount <= 0) {
    error_log("Validation failed - Entity: $legalEntityId, Count: $cameraCount");
    echo json_encode([
        'success' => false,
        'error' => 'Invalid parameters',
        'debug' => [
            'legal_entity_id' => $legalEntityId,
            'camera_count' => $cameraCount
        ]
    ]);
    exit;
}

try {
    // Get legal entity details
    $entity = $db->fetchOne(
        "SELECT id, legal_entity_name, payment_frequency, pricing_type, pricing_model
         FROM legal_entities
         WHERE id = :id",
        ['id' => $legalEntityId]
    );
    
    if (!$entity) {
        echo json_encode([
            'success' => false,
            'error' => 'Legal entity not found'
        ]);
        exit;
    }

    // Use the SELECTED camera count from the form, not the total active cameras
    // This ensures we price based on what the user selected for this invoice
    error_log("Using selected camera count: $cameraCount (main: $mainCameras, additional: $additionalCameras)");

    // Get pricing based on SELECTED camera count and invoice date
    $pricing = $pricingService->getPricingForEntity(
        $legalEntityId,
        $cameraCount,
        $invoiceDate
    );
    
    // Calculate total amount based on pricing model
    if ($pricing['pricing_type'] === 'first_plus_additional') {
        // First camera + additional model
        $amount = $pricing['total_cost'];
    } else {
        // Volume-based model: rate per camera * camera count
        $amount = $pricing['rate_to_use'] * $cameraCount;
    }

    $response = [
        'success' => true,
        'amount' => round($amount, 2),
        'camera_count' => $cameraCount,
        'main_cameras' => $mainCameras,
        'additional_cameras' => $additionalCameras,
        'pricing_model' => $pricing['pricing_type'],
        'rate_per_camera' => $pricing['rate_to_use'],
        'payment_frequency' => $entity['payment_frequency']
    ];

    error_log("Pricing calculation successful: " . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Pricing calculation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

