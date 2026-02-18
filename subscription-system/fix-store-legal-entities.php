<?php
// Direct MySQL connection for command-line script
$mysqli = new mysqli('localhost', 'root', 'root', 'facewatch_subscriptions');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "🔍 Checking Store Legal Entity Links\n";
echo "=====================================\n\n";

// Check Store 10
$result = $mysqli->query("SELECT * FROM stores WHERE store_id = '10'");
$store10 = $result->fetch_assoc();

if ($store10) {
    echo "Store 10 (Telford Frasers):\n";
    echo "  Current legal_entity_id: {$store10['legal_entity_id']}\n";
    echo "  Store Name: {$store10['store_name']}\n\n";

    // Check what legal entities exist
    $result = $mysqli->query("SELECT * FROM legal_entities ORDER BY id");
    echo "Available Legal Entities:\n";
    while ($le = $result->fetch_assoc()) {
        echo "  ID {$le['id']}: {$le['legal_entity_name']}\n";
    }
    echo "\n";

    // Check if Store 10 should be Frasers Group (ID 2)
    if ($store10['legal_entity_id'] == 10) {
        echo "⚠️  PROBLEM DETECTED!\n";
        echo "Store 10 is linked to legal_entity_id = 10\n";
        echo "But the invoice is for legal_entity_id = 2 (Frasers Group)\n\n";

        // Fix it
        echo "Fixing Store 10...\n";
        $mysqli->query("UPDATE stores SET legal_entity_id = 2 WHERE store_id = '10'");
        echo "✅ Store 10 updated to legal_entity_id = 2 (Frasers Group)\n\n";

        // Verify the fix
        $result = $mysqli->query("SELECT * FROM stores WHERE store_id = '10'");
        $store10After = $result->fetch_assoc();
        echo "Verification:\n";
        echo "  Store 10 legal_entity_id is now: {$store10After['legal_entity_id']}\n\n";

        // Check cameras
        $result = $mysqli->query(
            "SELECT ci.*, s.legal_entity_id
             FROM camera_installations ci
             JOIN stores s ON ci.store_id = s.id
             WHERE s.store_id = '10'"
        );
        $cameraCount = $result->num_rows;
        echo "Store 10 now has {$cameraCount} cameras linked to legal_entity_id = 2\n";

        echo "\n✅ FIX COMPLETE! Now refresh the allocation page and the cameras should appear.\n";
    } else {
        echo "✅ Store 10 is already correctly linked to legal_entity_id = {$store10['legal_entity_id']}\n";
    }
} else {
    echo "❌ Store 10 not found in database!\n";
}

$mysqli->close();
?>

