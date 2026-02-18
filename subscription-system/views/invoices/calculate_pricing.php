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

error_log("Parsed params - Entity: $legalEntityId, Count: $cameraCount, Main: $mainCameras, Additional: $additionalCameras");

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

    // Get TOTAL active cameras for this legal entity to determine pricing tier
    $asOfDate = date('Y-m-d');

    $sql = "SELECT COUNT(*) as total
            FROM camera_installations ci
            JOIN stores s ON ci.store_id = s.id
            WHERE s.legal_entity_id = :legal_entity_id
            AND ci.installation_date <= :as_of_date
            AND (ci.removal_date IS NULL OR ci.removal_date > :as_of_date2)";

    $params = [
        'legal_entity_id' => $legalEntityId,
        'as_of_date' => $asOfDate,
        'as_of_date2' => $asOfDate
    ];

    error_log("SQL: " . $sql);
    error_log("Params: " . print_r($params, true));

    $totalActiveCameras = $db->fetchOne($sql, $params);

    $totalCameraCount = intval($totalActiveCameras['total'] ?? 0);
    error_log("Total active cameras for entity $legalEntityId: $totalCameraCount");

    // Get pricing based on TOTAL camera count (for tier), not just cameras being invoiced
    $pricing = $pricingService->getPricingForEntity(
        $legalEntityId,
        $totalCameraCount,
        date('Y-m-d')
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

