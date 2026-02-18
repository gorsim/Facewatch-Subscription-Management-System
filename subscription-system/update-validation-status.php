<?php
/**
 * Update validation status column and validate all existing snapshots
 * Run this from command line: php update-validation-status.php
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Models/Model.php';
require_once __DIR__ . '/app/Models/CameraInstallation.php';
require_once __DIR__ . '/app/Services/CameraValidationService.php';

use App\Database;
use App\Services\CameraValidationService;

echo "=== Updating Validation Status and Validating Snapshots ===\n\n";

try {
    $db = Database::getInstance();
    echo "✅ Database connection successful\n\n";
    
    // Step 1: Update the enum to remove 'pending' and keep only 'matched' and 'mismatch'
    echo "Step 1: Updating validation_status column...\n";
    try {
        $db->query("
            ALTER TABLE camera_counts_monthly 
            MODIFY COLUMN validation_status ENUM('matched', 'mismatch') NULL DEFAULT NULL
        ");
        echo "✅ Validation status column updated (removed 'pending', only 'matched' and 'mismatch' allowed)\n\n";
    } catch (Exception $e) {
        echo "⚠️  " . $e->getMessage() . "\n\n";
    }
    
    // Step 2: Validate all existing snapshots
    echo "Step 2: Validating all existing camera count snapshots...\n";
    $validationService = new CameraValidationService();

    try {
        $results = $validationService->validateAllSnapshots();

        echo "✅ Validation complete!\n";
        echo "   - Total snapshots: {$results['total']}\n";
        echo "   - ✅ Matched: {$results['matched']}\n";
        echo "   - ❌ Mismatch: {$results['mismatch']}\n\n";
    } catch (Exception $e) {
        echo "Error during validation: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        throw $e;
    }
    
    // Step 3: Show summary of mismatches
    if ($results['mismatch'] > 0) {
        echo "Step 3: Showing mismatches...\n";
        $mismatches = $db->fetchAll("
            SELECT 
                ccm.id,
                ccm.month_date,
                s.store_id,
                s.store_name,
                le.legal_entity_name,
                ccm.cumulative_main_cameras as snapshot_main,
                ccm.cumulative_additional_cameras as snapshot_additional
            FROM camera_counts_monthly ccm
            JOIN stores s ON ccm.store_id = s.id
            JOIN legal_entities le ON s.legal_entity_id = le.id
            WHERE ccm.validation_status = 'mismatch'
            ORDER BY le.legal_entity_name, s.store_name, ccm.month_date
        ");
        
        foreach ($mismatches as $mismatch) {
            $details = $validationService->getValidationDetails($mismatch['id']);
            echo "\n❌ Mismatch found:\n";
            echo "   Legal Entity: {$mismatch['legal_entity_name']}\n";
            echo "   Store: {$mismatch['store_name']} ({$mismatch['store_id']})\n";
            echo "   Snapshot Date: " . date('d/m/Y', strtotime($mismatch['month_date'])) . "\n";
            echo "   Snapshot: {$mismatch['snapshot_main']} main + {$mismatch['snapshot_additional']} additional\n";
            echo "   Actual:   {$details['actual']['main_cameras']} main + {$details['actual']['additional_cameras']} additional\n";
            echo "   Difference: {$details['differences']['main']} main, {$details['differences']['additional']} additional\n";
        }
    }
    
    echo "\n✅ SUCCESS! All snapshots have been validated.\n";
    echo "From now on, all imported snapshots will be automatically validated.\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

