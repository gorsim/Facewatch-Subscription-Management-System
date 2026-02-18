<?php
/**
 * Check Camera Allocations - Diagnostic Tool
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "<h1>Camera Allocation Diagnostic</h1>";

// Get all cameras for Convenience Store
echo "<h2>Cameras for Convenience Store</h2>";
$cameras = $db->fetchAll(
    "SELECT c.*, s.store_name, le.legal_entity_name
     FROM camera_installations c
     JOIN stores s ON c.store_id = s.id
     JOIN legal_entities le ON s.legal_entity_id = le.id
     WHERE le.legal_entity_name LIKE '%Convenience%'
     ORDER BY c.id"
);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Camera ID</th><th>Store</th><th>Legal Entity</th><th>Install Date</th></tr>";
foreach ($cameras as $camera) {
    echo "<tr>";
    echo "<td>" . $camera['id'] . "</td>";
    echo "<td>" . htmlspecialchars($camera['store_name']) . "</td>";
    echo "<td>" . htmlspecialchars($camera['legal_entity_name']) . "</td>";
    echo "<td>" . $camera['installation_date'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check if these cameras have any allocations
echo "<h2>Camera Allocations</h2>";
$cameraIds = array_column($cameras, 'id');
if (!empty($cameraIds)) {
    $placeholders = implode(',', array_fill(0, count($cameraIds), '?'));
    $allocations = $db->fetchAll(
        "SELECT ica.*, i.invoice_number, i.invoice_status
         FROM invoice_camera_allocations ica
         LEFT JOIN invoices i ON ica.invoice_id = i.id
         WHERE ica.camera_installation_id IN ($placeholders)",
        $cameraIds
    );

    if (empty($allocations)) {
        echo "<p style='color: green; font-weight: bold;'>✓ No allocations found - cameras should be available!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>⚠️ Found allocations:</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Camera ID</th><th>Invoice ID</th><th>Invoice Number</th><th>Invoice Status</th></tr>";
        foreach ($allocations as $alloc) {
            echo "<tr>";
            echo "<td>" . $alloc['camera_installation_id'] . "</td>";
            echo "<td>" . $alloc['invoice_id'] . "</td>";
            echo "<td>" . ($alloc['invoice_number'] ?? '<span style="color:red;">DELETED/MISSING</span>') . "</td>";
            echo "<td>" . ($alloc['invoice_status'] ?? '<span style="color:red;">N/A</span>') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Offer to clean up orphaned allocations
        echo "<h3>Clean Up Orphaned Allocations</h3>";
        echo "<form method='POST'>";
        echo "<button type='submit' name='cleanup' style='background: red; color: white; padding: 10px; font-weight: bold;'>🗑️ Delete All Orphaned Allocations</button>";
        echo "</form>";
    }
}

// Handle cleanup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup'])) {
    try {
        $deleted = $db->execute(
            "DELETE FROM invoice_camera_allocations
             WHERE invoice_id NOT IN (SELECT id FROM invoices)"
        );
        echo "<p style='color: green; font-weight: bold;'>✓ Cleaned up orphaned allocations!</p>";
        echo "<p><a href='check_cameras.php'>Refresh page</a></p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
}

// Show what the create invoice query would return
echo "<h2>Available Cameras Query Test</h2>";
$legalEntityId = $db->fetchOne(
    "SELECT id FROM legal_entities WHERE legal_entity_name LIKE '%Convenience%'"
)['id'] ?? null;

if ($legalEntityId) {
    echo "<p>Legal Entity ID: $legalEntityId</p>";
    
    $availableCameras = $db->fetchAll(
        "SELECT c.id, c.installation_date, s.store_name
         FROM camera_installations c
         JOIN stores s ON c.store_id = s.id
         WHERE s.legal_entity_id = :legal_entity_id
         AND c.id NOT IN (
             SELECT camera_installation_id
             FROM invoice_camera_allocations
         )
         ORDER BY c.installation_date",
        ['legal_entity_id' => $legalEntityId]
    );
    
    echo "<p><strong>Available cameras count: " . count($availableCameras) . "</strong></p>";
    if (!empty($availableCameras)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Camera ID</th><th>Store</th><th>Install Date</th></tr>";
        foreach ($availableCameras as $cam) {
            echo "<tr>";
            echo "<td>" . $cam['id'] . "</td>";
            echo "<td>" . htmlspecialchars($cam['store_name']) . "</td>";
            echo "<td>" . $cam['installation_date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>No available cameras found!</p>";
    }
}

echo "<hr>";
echo "<p><a href='../public/index.php?page=invoices&action=create'>← Back to Create Invoice</a></p>";

