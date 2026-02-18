<?php
require_once __DIR__ . '/app/Database.php';

$db = new Database();

echo "<h2>🔍 Checking Store 10 Legal Entity</h2>";

// Check current legal entity for Store 10
$store10 = $db->fetchOne(
    "SELECT * FROM stores WHERE store_id = '10'",
    []
);

if ($store10) {
    echo "<p><strong>Current Store 10 Data:</strong></p>";
    echo "<pre>";
    print_r($store10);
    echo "</pre>";
    
    echo "<p>Store 10 is currently linked to <strong>legal_entity_id = {$store10['legal_entity_id']}</strong></p>";
    
    // Check what legal entities exist
    $legalEntities = $db->fetchAll("SELECT * FROM legal_entities ORDER BY id", []);
    echo "<h3>Available Legal Entities:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Main Rate</th><th>Additional Rate</th></tr>";
    foreach ($legalEntities as $le) {
        echo "<tr>";
        echo "<td>{$le['id']}</td>";
        echo "<td>{$le['legal_entity_name']}</td>";
        echo "<td>£{$le['main_camera_rate']}</td>";
        echo "<td>£{$le['additional_camera_rate']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // If Store 10 is linked to legal_entity_id = 10, but Frasers Group is ID 2, offer to fix it
    if ($store10['legal_entity_id'] == 10) {
        echo "<hr>";
        echo "<h3>⚠️ Problem Detected!</h3>";
        echo "<p>Store 10 (Telford Frasers) is linked to legal_entity_id = 10, but the invoice is for legal_entity_id = 2 (Frasers Group).</p>";
        
        // Check if legal_entity_id = 2 is Frasers Group
        $frasers = $db->fetchOne("SELECT * FROM legal_entities WHERE id = 2", []);
        if ($frasers) {
            echo "<p><strong>Legal Entity ID 2:</strong> {$frasers['legal_entity_name']}</p>";
            
            if (isset($_GET['fix']) && $_GET['fix'] === 'yes') {
                // Fix it
                $db->update('stores', 
                    ['legal_entity_id' => 2],
                    ['store_id' => '10']
                );
                echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
                echo "<h3>✅ Fixed!</h3>";
                echo "<p>Store 10 has been updated to legal_entity_id = 2 (Frasers Group)</p>";
                echo "<p><a href='?page=invoices&action=allocate&id=" . ($_GET['invoice_id'] ?? '') . "'>← Go back to allocation page</a></p>";
                echo "</div>";
            } else {
                echo "<p><strong>Should Store 10 be linked to Frasers Group (ID 2)?</strong></p>";
                echo "<p><a href='?fix=yes&invoice_id=" . ($_GET['invoice_id'] ?? '') . "' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>✅ Yes, fix it!</a></p>";
            }
        }
    }
} else {
    echo "<p style='color: red;'>❌ Store 10 not found in database!</p>";
}
?>

