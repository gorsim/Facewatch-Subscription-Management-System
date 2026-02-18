<?php
/**
 * Auto-Generate Invoices Cron Job
 * Run this daily to automatically generate repeat invoices
 * 
 * Usage:
 *   php cron/auto-generate-invoices.php
 * 
 * Cron schedule (daily at 2am):
 *   0 2 * * * cd /path/to/subscription-system && php cron/auto-generate-invoices.php >> logs/auto-generation.log 2>&1
 */

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/InvoiceAutoGenerationService.php';
require_once __DIR__ . '/../app/Services/InvoiceGenerationService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';

use App\Services\InvoiceAutoGenerationService;

echo "=== Invoice Auto-Generation Cron Job ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $service = new InvoiceAutoGenerationService();
    $results = $service->runAutoGeneration();
    
    echo "Results:\n";
    echo "  Checked: {$results['checked']} invoices\n";
    echo "  Generated: {$results['generated']} new invoices\n";
    echo "  Skipped: {$results['skipped']} (already generated)\n";
    echo "  Errors: {$results['errors']}\n\n";
    
    if (!empty($results['invoices'])) {
        echo "Details:\n";
        foreach ($results['invoices'] as $invoice) {
            echo "  - {$invoice['parent_number']}: {$invoice['status']}";
            if (isset($invoice['new_id'])) {
                echo " (New ID: {$invoice['new_id']})";
            }
            if (isset($invoice['error'])) {
                echo " - Error: {$invoice['error']}";
            }
            if (isset($invoice['reason'])) {
                echo " - {$invoice['reason']}";
            }
            echo "\n";
        }
    }
    
    echo "\n✅ Auto-generation completed successfully\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n❌ Auto-generation failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";
    
    exit(1);
}

