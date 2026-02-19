<?php
/**
 * Fix Camera Installation Dates
 * Corrects cameras where installation_date > removal_date (impossible scenario)
 * This happens when a camera is removed and re-installed in the same import
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "<h1>Fix Camera Installation Dates</h1>";
echo "<p>This script will fix cameras with impossible dates (installed after removal).</p>";

try {
    // Find cameras with installation_date > removal_date
    $brokenCameras = $db->fetchAll("
        SELECT 
            ci.id,
            ci.safr_code,
            ci.camera_name,
            ci.installation_date,
            ci.removal_date,
            s.store_name
        FROM camera_installations ci
        JOIN stores s ON ci.store_id = s.id
        WHERE ci.removal_date IS NOT NULL
        AND ci.installation_date > ci.removal_date
        ORDER BY ci.safr_code
    ");
    
    echo "<p>Found <strong>" . count($brokenCameras) . "</strong> cameras with incorrect dates.</p>";
    
    if (empty($brokenCameras)) {
        echo "<p style='color: green;'>✅ No cameras need fixing!</p>";
        exit;
    }
    
    echo "<h2>Cameras to Fix:</h2>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>SAFR Code</th><th>Store</th><th>Current Install Date</th><th>Removal Date</th><th>Action</th></tr>";
    
    $fixed = 0;
    
    foreach ($brokenCameras as $camera) {
        echo "<tr>";
        echo "<td><strong>{$camera['safr_code']}</strong></td>";
        echo "<td>{$camera['store_name']}</td>";
        echo "<td>{$camera['installation_date']}</td>";
        echo "<td>{$camera['removal_date']}</td>";
        
        // For cameras that were removed and re-installed at the same store,
        // we should clear the removal_date (they're active again)
        // The installation_date should stay as the ORIGINAL installation date
        
        // Since we don't have the original installation date, we'll use the removal_date
        // as a proxy (the camera must have been installed before it was removed)
        $originalInstallDate = date('Y-m-d', strtotime($camera['removal_date'] . ' -1 year'));
        
        $db->update('camera_installations', [
            'installation_date' => $originalInstallDate,
            'removal_date' => NULL  // Clear removal since it's been re-installed
        ], 'id = :id', ['id' => $camera['id']]);
        
        echo "<td style='color: green;'>✅ Fixed: Set install date to {$originalInstallDate}, cleared removal</td>";
        echo "</tr>";
        
        $fixed++;
    }
    
    echo "</table>";
    
    echo "<h2 style='color: green;'>✅ Fixed {$fixed} cameras!</h2>";
    echo "<p><a href='cameras.php'>← Back to Cameras</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

