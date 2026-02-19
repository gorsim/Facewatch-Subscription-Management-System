<?php
/**
 * Cleanup Duplicate Camera Installations
 * Removes duplicate cameras with the same SAFR code, keeping only the most recent one
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "<h1>Camera Duplicate Cleanup</h1>";
echo "<p>This script will remove duplicate camera installations with the same SAFR code.</p>";

try {
    $db->beginTransaction();
    
    // Step 1: Find all duplicate SAFR codes
    echo "<h2>Step 1: Finding duplicates...</h2>";
    $duplicates = $db->fetchAll("
        SELECT safr_code, COUNT(*) as count, GROUP_CONCAT(id ORDER BY id DESC) as ids
        FROM camera_installations 
        WHERE safr_code IS NOT NULL AND safr_code != ''
        GROUP BY safr_code 
        HAVING COUNT(*) > 1
        ORDER BY count DESC
    ");
    
    echo "<p>Found " . count($duplicates) . " SAFR codes with duplicates</p>";
    
    $totalDeleted = 0;
    $totalUpdated = 0;
    
    // Step 2: For each duplicate set, keep the newest and delete the rest
    foreach ($duplicates as $dup) {
        $ids = explode(',', $dup['ids']);
        $keepId = $ids[0]; // Keep the newest (highest ID)
        $deleteIds = array_slice($ids, 1); // Delete the rest
        
        echo "<p><strong>SAFR Code: {$dup['safr_code']}</strong> - {$dup['count']} duplicates</p>";
        echo "<ul>";
        echo "<li>Keeping ID: {$keepId}</li>";
        echo "<li>Deleting IDs: " . implode(', ', $deleteIds) . "</li>";
        
        // Update any invoice allocations pointing to old IDs to point to the new ID
        foreach ($deleteIds as $oldId) {
            $updated = $db->query("
                UPDATE invoice_camera_allocations 
                SET camera_installation_id = :new_id 
                WHERE camera_installation_id = :old_id
            ", [
                'new_id' => $keepId,
                'old_id' => $oldId
            ]);
            
            if ($updated > 0) {
                echo "<li>Updated {$updated} invoice allocations from ID {$oldId} to {$keepId}</li>";
                $totalUpdated += $updated;
            }
        }
        
        // Delete the old camera records
        $deleted = $db->query("
            DELETE FROM camera_installations 
            WHERE id IN (" . implode(',', $deleteIds) . ")
        ");
        
        echo "<li>Deleted {$deleted} duplicate camera records</li>";
        echo "</ul>";
        
        $totalDeleted += $deleted;
    }
    
    echo "<h2>Step 3: Adding unique constraint...</h2>";
    
    // Add unique constraint to prevent future duplicates
    $db->query("
        ALTER TABLE camera_installations 
        ADD UNIQUE INDEX unique_safr_code (safr_code)
    ");
    
    echo "<p style='color: green;'>✓ Added unique constraint on safr_code</p>";
    
    $db->commit();
    
    echo "<h2>✅ Cleanup Complete!</h2>";
    echo "<ul>";
    echo "<li><strong>{$totalDeleted}</strong> duplicate camera records deleted</li>";
    echo "<li><strong>{$totalUpdated}</strong> invoice allocations updated</li>";
    echo "<li>Unique constraint added to prevent future duplicates</li>";
    echo "</ul>";
    
    echo "<p><a href='cameras.php'>← Back to Cameras</a></p>";
    
} catch (Exception $e) {
    $db->rollback();
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p>No changes were made to the database.</p>";
}

