<?php
/**
 * Save Pricing Type to Session
 * AJAX endpoint to preserve pricing_type selection when navigating to/from pricing pages
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $legalEntityId = $_POST['legal_entity_id'] ?? null;
    $pricingType = $_POST['pricing_type'] ?? 'default';
    
    if ($legalEntityId) {
        $_SESSION['edit_pricing_type_' . $legalEntityId] = $pricingType;
        error_log("Saved pricing_type '$pricingType' to session for legal entity $legalEntityId");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing legal_entity_id']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
exit;

