<?php
/**
 * Test the date calculation logic
 */

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/InvoiceAutoGenerationService.php';
require_once __DIR__ . '/../app/Services/InvoiceGenerationService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';

use App\Database;
use App\Services\InvoiceAutoGenerationService;

$db = Database::getInstance();
$service = new InvoiceAutoGenerationService($db);

echo "=== Testing Date Calculation ===\n\n";

// Test with INV-135's date (2025-10-31)
$baseDate = '2025-10-31';
$frequency = 'monthly';

echo "Base date: {$baseDate}\n";
echo "Frequency: {$frequency}\n\n";

echo "Calculating next dates:\n";
for ($period = 1; $period <= 12; $period++) {
    // Use reflection to call the private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('calculateNextDate');
    $method->setAccessible(true);
    
    $nextDate = $method->invoke($service, $baseDate, $period, $frequency);
    echo "Period {$period}: {$nextDate}\n";
}

