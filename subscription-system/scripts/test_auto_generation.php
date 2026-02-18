<?php
/**
 * Test Auto-Generation Script
 * Run this to manually test the auto-generation system
 * 
 * Usage:
 *   php scripts/test_auto_generation.php [date]
 * 
 * Examples:
 *   php scripts/test_auto_generation.php              # Use today's date
 *   php scripts/test_auto_generation.php 2025-12-01   # Use specific date
 */

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/InvoiceAutoGenerationService.php';
require_once __DIR__ . '/../app/Services/InvoiceGenerationService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';

use App\Services\InvoiceAutoGenerationService;

// Get date from command line or use today
$asOfDate = $argv[1] ?? date('Y-m-d');

echo "=== Testing Invoice Auto-Generation ===\n";
echo "As of date: {$asOfDate}\n";
echo "Current date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $service = new InvoiceAutoGenerationService();
    
    echo "Running auto-generation...\n\n";
    $results = $service->runAutoGeneration($asOfDate);
    
    echo "Results:\n";
    echo "  Checked: {$results['checked']} invoices\n";
    echo "  Generated: {$results['generated']} new invoices\n";
    echo "  Converted: {$results['converted']} forecasts to actual\n";
    echo "  Skipped: {$results['skipped']} (already generated)\n";
    echo "  Errors: {$results['errors']}\n\n";
    
    if (!empty($results['invoices'])) {
        echo "Details:\n";
        foreach ($results['invoices'] as $invoice) {
            $number = $invoice['invoice_number'] ?? $invoice['parent_number'] ?? 'Unknown';
            echo "  - {$number}: {$invoice['status']}";
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
    
    echo "\n✅ Test completed successfully\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";
    
    exit(1);
}

