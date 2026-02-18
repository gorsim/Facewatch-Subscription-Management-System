<?php
/**
 * Debug forecast generation
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

// Get invoice INV-001
$parent = $db->fetchOne("SELECT * FROM invoices WHERE invoice_number = 'INV-001'");

echo "Parent Invoice:" . PHP_EOL;
echo "  ID: {$parent['id']}" . PHP_EOL;
echo "  Number: {$parent['invoice_number']}" . PHP_EOL;
echo "  Date: {$parent['invoice_date']}" . PHP_EOL;
echo "  Amount: {$parent['invoice_amount']}" . PHP_EOL;
echo "  Frequency: {$parent['payment_frequency']}" . PHP_EOL;
echo "  is_forecast: " . ($parent['is_forecast'] ? 'TRUE' : 'FALSE') . PHP_EOL;
echo PHP_EOL;

// Check if it's a forecast
if ($parent['is_forecast']) {
    echo "❌ This is already a forecast invoice - cannot generate forecasts from it" . PHP_EOL;
    exit;
}

// Check legal entity
$legalEntity = $db->fetchOne("
    SELECT termination_date FROM legal_entities WHERE id = :id
", ['id' => $parent['legal_entity_id']]);

$terminationDate = $legalEntity['termination_date'] ?? null;
echo "Legal Entity Termination Date: " . ($terminationDate ?? 'NULL') . PHP_EOL;
echo PHP_EOL;

// Calculate forecast dates
$yearsAhead = 3;
for ($year = 1; $year <= $yearsAhead; $year++) {
    $date = new DateTime($parent['invoice_date']);
    $date->modify("+{$year} year");
    $forecastDate = $date->format('Y-m-d');
    
    echo "Year {$year}:" . PHP_EOL;
    echo "  Forecast Date: {$forecastDate}" . PHP_EOL;
    
    // Check termination
    if ($terminationDate && $forecastDate > $terminationDate) {
        echo "  ❌ STOPPED: Forecast date is after termination ({$terminationDate})" . PHP_EOL;
        break;
    }
    
    // Calculate price
    $forecastYear = (int)date('Y', strtotime($forecastDate));
    $baseYear = (int)date('Y', strtotime($parent['invoice_date']));
    $yearsElapsed = $forecastYear - $baseYear;
    $forecastPrice = $parent['invoice_amount'] * pow(1.03, $yearsElapsed);
    
    echo "  Price: £{$forecastPrice} (base: £{$parent['invoice_amount']}, inflation: {$yearsElapsed} years @ 3%)" . PHP_EOL;
    echo "  ✅ Would create forecast" . PHP_EOL;
    echo PHP_EOL;
}

