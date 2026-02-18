<?php
/**
 * Complete Invoice Reset Script
 * 
 * This script performs a COMPLETE reset of all invoice data:
 * - Deletes ALL invoices (actual, draft, forecast, issued - everything)
 * - Deletes ALL camera allocations
 * - Deletes ALL invoice generation logs
 * 
 * KEEPS:
 * - Camera installations
 * - Legal entities
 * - Stores
 * - Pricing tiers
 * 
 * After running this, you'll need to:
 * 1. Import Xero invoices via CSV
 * 2. Manually allocate cameras to invoices
 * 3. System will auto-generate forecasts from there
 * 
 * Usage:
 *   php scripts/complete_invoice_reset.php
 */

require_once __DIR__ . '/../app/Database.php';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         COMPLETE INVOICE RESET - DANGER ZONE!              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$db = App\Database::getInstance();

try {
    // Get counts before deletion
    echo "📊 Current Database Status:\n";
    echo "─────────────────────────────────────────────────────────────\n";
    
    $invoiceCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoices")['count'];
    $forecastCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoices WHERE is_forecast = 1")['count'];
    $actualCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoices WHERE is_forecast = 0")['count'];
    $allocationCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoice_camera_allocations")['count'];
    $logCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoice_generation_log")['count'];
    
    echo "  Total Invoices: $invoiceCount\n";
    echo "    - Forecast: $forecastCount\n";
    echo "    - Actual: $actualCount\n";
    echo "  Camera Allocations: $allocationCount\n";
    echo "  Generation Logs: $logCount\n\n";
    
    // Confirmation
    echo "⚠️  WARNING: This will DELETE ALL of the above data!\n\n";
    echo "What will be KEPT:\n";
    echo "  ✅ Camera installations\n";
    echo "  ✅ Legal entities\n";
    echo "  ✅ Stores\n";
    echo "  ✅ Pricing tiers\n\n";
    
    echo "After reset, you'll need to:\n";
    echo "  1. Import Xero invoices (CSV)\n";
    echo "  2. Allocate cameras to invoices\n";
    echo "  3. System will auto-generate forecasts\n\n";
    
    echo "Type 'DELETE ALL' to proceed: ";
    $confirmation = trim(fgets(STDIN));
    
    if ($confirmation !== 'DELETE ALL') {
        echo "\n❌ Reset cancelled. No changes made.\n";
        exit(0);
    }
    
    echo "\n🗑️  Starting complete reset...\n";
    echo "─────────────────────────────────────────────────────────────\n";
    
    // Step 1: Delete camera allocations
    echo "Step 1: Deleting camera allocations...\n";
    $db->query("DELETE FROM invoice_camera_allocations");
    echo "  ✅ Deleted $allocationCount camera allocations\n\n";
    
    // Step 2: Delete generation logs
    echo "Step 2: Deleting invoice generation logs...\n";
    $db->query("DELETE FROM invoice_generation_log");
    echo "  ✅ Deleted $logCount generation logs\n\n";
    
    // Step 3: Delete ALL invoices
    echo "Step 3: Deleting ALL invoices...\n";
    $db->query("DELETE FROM invoices");
    echo "  ✅ Deleted $invoiceCount invoices\n\n";
    
    // Step 4: Reset auto-increment (optional, for clean IDs)
    echo "Step 4: Resetting auto-increment counters...\n";
    $db->query("ALTER TABLE invoices AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE invoice_camera_allocations AUTO_INCREMENT = 1");
    $db->query("ALTER TABLE invoice_generation_log AUTO_INCREMENT = 1");
    echo "  ✅ Reset auto-increment counters\n\n";
    
    // Verify deletion
    echo "📊 Final Database Status:\n";
    echo "─────────────────────────────────────────────────────────────\n";
    
    $finalInvoiceCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoices")['count'];
    $finalAllocationCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoice_camera_allocations")['count'];
    $finalLogCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoice_generation_log")['count'];
    
    echo "  Total Invoices: $finalInvoiceCount\n";
    echo "  Camera Allocations: $finalAllocationCount\n";
    echo "  Generation Logs: $finalLogCount\n\n";
    
    // Check what's still there
    $cameraCount = $db->fetchOne("SELECT COUNT(*) as count FROM camera_installations")['count'];
    $legalEntityCount = $db->fetchOne("SELECT COUNT(*) as count FROM legal_entities")['count'];
    $storeCount = $db->fetchOne("SELECT COUNT(*) as count FROM stores")['count'];
    $pricingCount = $db->fetchOne("SELECT COUNT(*) as count FROM pricing_tiers")['count'];
    
    echo "✅ Data Preserved:\n";
    echo "  Camera Installations: $cameraCount\n";
    echo "  Legal Entities: $legalEntityCount\n";
    echo "  Stores: $storeCount\n";
    echo "  Pricing Tiers: $pricingCount\n\n";
    
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║              ✅ COMPLETE RESET SUCCESSFUL!                 ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📋 Next Steps:\n";
    echo "  1. Go to: Import → Xero Invoices → Upload CSV\n";
    echo "  2. Allocate cameras to the imported invoices\n";
    echo "  3. System will auto-generate forecast invoices\n\n";
    
    echo "Completed: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Reset failed. Database may be in an inconsistent state.\n";
    exit(1);
}

