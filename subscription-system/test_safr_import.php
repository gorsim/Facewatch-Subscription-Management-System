<?php
/**
 * Test SAFR Code Import
 * Verify that SAFR codes are being imported correctly
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "=== Testing SAFR Code Import ===\n\n";

// Get the most recently imported cameras
$cameras = $db->fetchAll("
    SELECT 
        ci.*,
        s.store_name,
        s.store_id as store_code
    FROM camera_installations ci
    JOIN stores s ON ci.store_id = s.id
    ORDER BY ci.id DESC
    LIMIT 10
");

if (empty($cameras)) {
    echo "No cameras found in database.\n";
    exit;
}

echo "Last 10 cameras imported:\n";
echo str_repeat("=", 120) . "\n";
printf("%-5s %-20s %-15s %-20s %-20s %-15s\n", 
    "ID", "Store", "Store Code", "Camera Name", "SAFR Code", "Install Date"
);
echo str_repeat("=", 120) . "\n";

foreach ($cameras as $camera) {
    printf("%-5s %-20s %-15s %-20s %-20s %-15s\n",
        $camera['id'],
        substr($camera['store_name'], 0, 20),
        $camera['store_code'],
        $camera['camera_name'] ?: '(not set)',
        $camera['safr_code'] ?: '(not set)',
        $camera['installation_date']
    );
}

echo str_repeat("=", 120) . "\n\n";

// Check for cameras with specific store IDs from the CSV
$testStoreIds = ['1919', '650', '1063', '1535'];

echo "Checking cameras for stores in your CSV:\n";
echo str_repeat("-", 120) . "\n";

foreach ($testStoreIds as $storeId) {
    $store = $db->fetchOne("SELECT * FROM stores WHERE store_id = :store_id", ['store_id' => $storeId]);
    
    if (!$store) {
        echo "Store $storeId: NOT FOUND in database\n";
        continue;
    }
    
    $storeCameras = $db->fetchAll(
        "SELECT * FROM camera_installations WHERE store_id = :id ORDER BY id DESC LIMIT 1",
        ['id' => $store['id']]
    );
    
    if (empty($storeCameras)) {
        echo "Store $storeId ({$store['store_name']}): No cameras found\n";
    } else {
        $cam = $storeCameras[0];
        echo "Store $storeId ({$store['store_name']}): ";
        echo "Camera Name='" . ($cam['camera_name'] ?: 'NOT SET') . "', ";
        echo "SAFR Code='" . ($cam['safr_code'] ?: 'NOT SET') . "'\n";
    }
}

echo str_repeat("-", 120) . "\n";

