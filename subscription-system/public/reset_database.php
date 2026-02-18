<?php
/**
 * Database Reset Tool
 * Clears all data from the subscription system tables
 * USE WITH CAUTION - THIS DELETES ALL DATA!
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

// Check if user confirmed
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$confirmed) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Reset Database</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .warning { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .danger { background: #f8d7da; border: 2px solid #dc3545; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; border-radius: 4px; text-decoration: none; font-weight: bold; }
            .btn-danger { background: #dc3545; color: white; }
            .btn-secondary { background: #6c757d; color: white; }
            h1 { color: #dc3545; }
            ul { line-height: 1.8; }
        </style>
    </head>
    <body>
        <h1>⚠️ Reset Database</h1>
        
        <div class="danger">
            <h2>WARNING: This will delete ALL data!</h2>
            <p>This action will permanently delete all data from the following tables:</p>
            <ul>
                <li><strong>Xero Import Sessions</strong> - All import history</li>
                <li><strong>Xero Imported Invoices</strong> - All imported Xero invoices</li>
                <li><strong>Matching Audit Log</strong> - All matching decisions</li>
                <li><strong>Invoice Camera Allocations</strong> - All camera assignments</li>
                <li><strong>Invoices</strong> - All system-generated invoices</li>
                <li><strong>Invoice Status History</strong> - All status change history</li>
                <li><strong>Invoice Generation Log</strong> - All generation history</li>
                <li><strong>Camera Installations</strong> - All cameras</li>
                <li><strong>Stores</strong> - All store locations</li>
                <li><strong>Legal Entity Contracts</strong> - All contracts</li>
                <li><strong>Legal Entities</strong> - All customers</li>
                <li><strong>Legal Entity Pricing</strong> - All custom pricing</li>
                <li><strong>Legal Entity Independent Pricing</strong> - All independent pricing</li>
                <li><strong>Camera Pricing</strong> - All camera-specific pricing</li>
            </ul>
            <p><strong>This action CANNOT be undone!</strong></p>
        </div>

        <div class="warning">
            <h3>What will NOT be deleted:</h3>
            <ul>
                <li>Pricing Tiers</li>
                <li>Users</li>
            </ul>
            <p>Everything else will be completely cleared for a fresh start.</p>
        </div>

        <h3>Are you absolutely sure?</h3>
        <p>
            <a href="?confirm=yes" class="btn btn-danger" onclick="return confirm('Are you REALLY sure? This cannot be undone!');">
                🗑️ Yes, Delete All Invoice Data
            </a>
            <a href="../public/?page=invoices" class="btn btn-secondary">
                ← Cancel, Go Back
            </a>
        </p>
    </body>
    </html>
    <?php
    exit;
}

// User confirmed - proceed with deletion
echo "<!DOCTYPE html><html><head><title>Resetting Database...</title></head><body>";
echo "<h1>Resetting Database...</h1>";
echo "<pre>";

try {
    // Disable foreign key checks temporarily
    $db->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Delete in correct order (respecting foreign keys)
    echo "1. Deleting matching audit log...\n";
    $result = $db->query("DELETE FROM matching_audit_log");
    echo "   ✓ Deleted\n\n";

    echo "2. Deleting Xero imported invoices...\n";
    $result = $db->query("DELETE FROM xero_imported_invoices");
    echo "   ✓ Deleted\n\n";

    echo "3. Deleting Xero import sessions...\n";
    $result = $db->query("DELETE FROM xero_import_sessions");
    echo "   ✓ Deleted\n\n";

    echo "4. Deleting invoice camera allocations...\n";
    $result = $db->query("DELETE FROM invoice_camera_allocations");
    echo "   ✓ Deleted\n\n";

    echo "5. Deleting invoice status history...\n";
    $result = $db->query("DELETE FROM invoice_status_history");
    echo "   ✓ Deleted\n\n";

    echo "6. Deleting invoices...\n";
    $result = $db->query("DELETE FROM invoices");
    echo "   ✓ Deleted\n\n";

    echo "7. Deleting invoice generation log...\n";
    $result = $db->query("DELETE FROM invoice_generation_log");
    echo "   ✓ Deleted\n\n";

    echo "8. Deleting camera pricing...\n";
    $result = $db->query("DELETE FROM camera_pricing");
    echo "   ✓ Deleted\n\n";

    echo "9. Deleting legal entity independent pricing...\n";
    $result = $db->query("DELETE FROM legal_entity_independent_pricing");
    echo "   ✓ Deleted\n\n";

    echo "10. Deleting legal entity pricing...\n";
    $result = $db->query("DELETE FROM legal_entity_pricing");
    echo "   ✓ Deleted\n\n";

    echo "11. Deleting camera installations...\n";
    $result = $db->query("DELETE FROM camera_installations");
    echo "   ✓ Deleted\n\n";

    echo "12. Deleting stores...\n";
    $result = $db->query("DELETE FROM stores");
    echo "   ✓ Deleted\n\n";

    echo "13. Deleting legal entity contracts...\n";
    $result = $db->query("DELETE FROM legal_entity_contracts");
    echo "   ✓ Deleted\n\n";

    echo "14. Deleting legal entities...\n";
    $result = $db->query("DELETE FROM legal_entities");
    echo "   ✓ Deleted\n\n";
    
    // Reset auto-increment counters
    echo "15. Resetting auto-increment counters...\n";
    $db->query("ALTER TABLE xero_import_sessions AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE xero_imported_invoices AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE matching_audit_log AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE invoice_camera_allocations AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE invoices AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE invoice_status_history AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE invoice_generation_log AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE camera_installations AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE stores AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE legal_entity_contracts AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE legal_entities AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE camera_pricing AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE legal_entity_pricing AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE legal_entity_independent_pricing AUTO_INCREMENT = 1");
    echo "   ✓ Reset\n\n";
    
    // Re-enable foreign key checks
    $db->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n✅ DATABASE RESET COMPLETE!\n\n";
    echo "All data has been deleted - completely fresh start!\n";
    echo "Only pricing tiers and users remain.\n\n";
    
    echo "</pre>";
    echo "<h2>✅ Success!</h2>";
    echo "<p><a href='../public/?page=invoices' style='display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;'>Go to Invoices</a></p>";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "</pre>";
    echo "<p><a href='../public/?page=invoices'>Go Back</a></p>";
}

echo "</body></html>";

