<?php
/**
 * Check Telford camera data
 */

require_once __DIR__ . '/app/bootstrap.php';

use App\Database;
use App\Models\CameraInstallation;

$db = Database::getInstance();

// Get Telford store
$telford = $db->fetchOne(
    "SELECT * FROM stores WHERE store_name LIKE '%Telford%'"
);

if (!$telford) {
    echo "Telford store not found\n";
    exit;
}

echo "=== TELFORD STORE ===\n";
echo "Store ID: {$telford['id']}\n";
echo "Store Name: {$telford['store_name']}\n\n";

// Get snapshots
echo "=== CAMERA SNAPSHOTS ===\n";
$snapshots = $db->fetchAll(
    "SELECT month_date, cumulative_main_cameras, cumulative_additional_cameras, validation_status
     FROM camera_counts_monthly
     WHERE store_id = :store_id
     ORDER BY month_date DESC
     LIMIT 5",
    ['store_id' => $telford['id']]
);

foreach ($snapshots as $snapshot) {
    echo "Date: {$snapshot['month_date']} | Main: {$snapshot['cumulative_main_cameras']} | Additional: {$snapshot['cumulative_additional_cameras']} | Status: {$snapshot['validation_status']}\n";
    
    // Get actual counts for this date
    $counts = CameraInstallation::getCountByStore($telford['id'], $snapshot['month_date']);
    echo "  -> Actual: Main: {$counts['main']} | Additional: {$counts['additional']}\n";
    echo "  -> Match: " . (($counts['main'] == $snapshot['cumulative_main_cameras'] && $counts['additional'] == $snapshot['cumulative_additional_cameras']) ? 'YES' : 'NO') . "\n\n";
}

// Get all installations
echo "\n=== CAMERA INSTALLATIONS ===\n";
$installations = $db->fetchAll(
    "SELECT id, installation_date, removal_date, camera_type
     FROM camera_installations
     WHERE store_id = :store_id
     ORDER BY installation_date, camera_type",
    ['store_id' => $telford['id']]
);

foreach ($installations as $inst) {
    $status = $inst['removal_date'] ? "REMOVED on {$inst['removal_date']}" : "ACTIVE";
    echo "ID: {$inst['id']} | Date: {$inst['installation_date']} | Type: {$inst['camera_type']} | Status: {$status}\n";
}

