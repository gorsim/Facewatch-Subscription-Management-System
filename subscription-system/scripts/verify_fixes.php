<?php
/**
 * Verify that the forecast duplication fixes are in place
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "🔍 VERIFYING FORECAST DUPLICATION FIXES\n";
echo "==========================================\n\n";

// Check 1: Verify converted forecasts have parent_invoice_id = NULL
echo "Check 1: Converted Forecasts\n";
echo "-----------------------------\n";

$convertedWithParent = $db->fetchAll("
    SELECT 
        id,
        invoice_number,
        parent_invoice_id,
        invoice_status,
        is_forecast
    FROM invoices
    WHERE is_forecast = 0
    AND parent_invoice_id IS NOT NULL
    LIMIT 10
");

if (empty($convertedWithParent)) {
    echo "✅ PASS: No converted forecasts have parent_invoice_id set\n";
    echo "   (All actual invoices have parent_invoice_id = NULL)\n\n";
} else {
    echo "❌ FAIL: Found converted forecasts with parent_invoice_id:\n\n";
    echo "Invoice #    | Parent ID | Status\n";
    echo "-------------|-----------|----------\n";
    foreach ($convertedWithParent as $inv) {
        printf("%-12s | %-9s | %s\n",
            $inv['invoice_number'],
            $inv['parent_invoice_id'],
            $inv['invoice_status']
        );
    }
    echo "\n⚠️  These invoices should have parent_invoice_id = NULL\n\n";
}

// Check 2: Look for duplicate forecast chains
echo "Check 2: Duplicate Forecast Chains\n";
echo "-----------------------------------\n";

$duplicateChains = $db->fetchAll("
    SELECT 
        parent_invoice_id,
        invoice_date,
        COUNT(*) as count
    FROM invoices
    WHERE is_forecast = 1
    GROUP BY parent_invoice_id, invoice_date
    HAVING count > 1
    LIMIT 10
");

if (empty($duplicateChains)) {
    echo "✅ PASS: No duplicate forecasts found\n";
    echo "   (No two forecasts with same parent + same date)\n\n";
} else {
    echo "❌ FAIL: Found duplicate forecast chains:\n\n";
    echo "Parent ID | Invoice Date | Duplicate Count\n";
    echo "----------|--------------|----------------\n";
    foreach ($duplicateChains as $dup) {
        printf("%9s | %-12s | %d\n",
            $dup['parent_invoice_id'],
            $dup['invoice_date'],
            $dup['count']
        );
    }
    echo "\n";
}

// Check 3: Show forecast counts per parent
echo "Check 3: Forecast Counts\n";
echo "------------------------\n";

$forecastCounts = $db->fetchAll("
    SELECT 
        i.invoice_number,
        i.invoice_date,
        i.payment_frequency,
        COUNT(f.id) as forecast_count
    FROM invoices i
    LEFT JOIN invoices f ON f.parent_invoice_id = i.id AND f.is_forecast = 1
    WHERE i.is_forecast = 0
    AND i.parent_invoice_id IS NULL
    GROUP BY i.id
    ORDER BY i.invoice_date
    LIMIT 10
");

echo "Invoice #    | Date       | Frequency | Forecasts\n";
echo "-------------|------------|-----------|----------\n";
foreach ($forecastCounts as $fc) {
    printf("%-12s | %-10s | %-9s | %d\n",
        $fc['invoice_number'],
        $fc['invoice_date'],
        $fc['payment_frequency'],
        $fc['forecast_count']
    );
}

echo "\n";

// Summary
echo "==========================================\n";
echo "SUMMARY\n";
echo "==========================================\n\n";

if (empty($convertedWithParent) && empty($duplicateChains)) {
    echo "✅ ALL CHECKS PASSED!\n";
    echo "   The fixes are working correctly.\n\n";
} else {
    echo "❌ SOME CHECKS FAILED\n";
    echo "   There may be data issues or the fixes aren't working.\n\n";
}

echo "Next steps:\n";
echo "1. If checks passed but you're still seeing issues, try:\n";
echo "   - Clear browser cache\n";
echo "   - Restart MAMP to clear PHP cache\n";
echo "   - Reset database and reimport\n\n";
echo "2. If checks failed, there may be old data:\n";
echo "   - Reset database to start fresh\n";
echo "   - The fixes will prevent new duplicates\n\n";

