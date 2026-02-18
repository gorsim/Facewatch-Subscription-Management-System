<!DOCTYPE html>
<html>
<head>
    <title>Henderson Configuration Check</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        .label { font-weight: bold; color: #555; }
        .value { color: #007bff; }
        .null { color: #dc3545; font-style: italic; }
        .rate { margin: 10px 0; padding: 10px; background: #f8f9fa; border-left: 3px solid #28a745; }
        .port-note { background: #fff3cd; padding: 10px; margin: 10px 0; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
<div class="port-note">
    <strong>📍 Correct URL:</strong> http://localhost:8080/subscription-system/check_henderson.php
</div>
<?php
require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

// Check Henderson Retail Limited configuration
$sql = "SELECT id, legal_entity_name, pricing_type, pricing_model FROM legal_entities WHERE legal_entity_name LIKE '%Henderson%'";
$stmt = $db->query($sql);
$entity = $stmt->fetch(PDO::FETCH_ASSOC);

echo '<div class="section">';
echo '<h2>Henderson Retail Limited Configuration</h2>';
if ($entity) {
    echo '<p><span class="label">ID:</span> <span class="value">' . $entity['id'] . '</span></p>';
    echo '<p><span class="label">Name:</span> <span class="value">' . $entity['legal_entity_name'] . '</span></p>';
    echo '<p><span class="label">Pricing Type:</span> ';
    if ($entity['pricing_type']) {
        echo '<span class="value">' . $entity['pricing_type'] . '</span>';
    } else {
        echo '<span class="null">NULL (not set)</span>';
    }
    echo '</p>';
    echo '<p><span class="label">Pricing Model:</span> ';
    if ($entity['pricing_model']) {
        echo '<span class="value">' . $entity['pricing_model'] . '</span>';
    } else {
        echo '<span class="null">NULL (not set)</span>';
    }
    echo '</p>';
} else {
    echo '<p class="null">Entity not found!</p>';
}
echo '</div>';

echo '<div class="section">';
echo '<h2>Independent Pricing Rates (Recent 5)</h2>';
$sql2 = "SELECT * FROM independent_pricing ORDER BY effective_date DESC LIMIT 5";
$stmt2 = $db->query($sql2);
$rates = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if ($rates) {
    foreach ($rates as $rate) {
        echo '<div class="rate">';
        echo '<p><span class="label">Effective Date:</span> <span class="value">' . $rate['effective_date'] . '</span></p>';
        echo '<p><span class="label">First Camera Annual:</span> £' . number_format($rate['first_camera_price_per_annum'], 2) . '</p>';
        echo '<p><span class="label">Additional Camera Annual:</span> £' . number_format($rate['additional_camera_price_per_annum'], 2) . '</p>';
        echo '<p><span class="label">First Camera Quarterly:</span> £' . number_format($rate['first_camera_price_per_quarter'], 2) . '</p>';
        echo '<p><span class="label">Additional Camera Quarterly:</span> £' . number_format($rate['additional_camera_price_per_quarter'], 2) . '</p>';
        echo '</div>';
    }
} else {
    echo '<p class="null">No independent pricing rates found!</p>';
}
echo '</div>';
?>
</body>
</html>

