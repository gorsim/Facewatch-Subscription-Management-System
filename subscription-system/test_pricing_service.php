<?php
/**
 * Test Pricing Service
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/PricingService.php';

use App\Database;
use App\Services\PricingService;

echo "<h1>Testing Pricing Service</h1>\n";

try {
    $db = Database::getInstance();
    $pricingService = new PricingService();
    
    // Get B&M (custom pricing)
    $bm = $db->fetchOne("SELECT id, legal_entity_name FROM legal_entities WHERE legal_entity_name LIKE '%B & M%'");
    
    // Get their camera count
    $cameraCount = $db->fetchOne("
        SELECT COUNT(*) as total
        FROM camera_installations ci
        JOIN stores s ON ci.store_id = s.id
        WHERE s.legal_entity_id = :id
        AND ci.removal_date IS NULL
    ", ['id' => $bm['id']]);
    
    echo "<h2>B&M Retail Limited</h2>\n";
    echo "<p><strong>Current Cameras:</strong> {$cameraCount['total']}</p>\n";
    
    // Test with current camera count
    $pricing = $pricingService->getPricingForEntity($bm['id'], (int)$cameraCount['total']);
    
    echo "<h3>Current Pricing Tier</h3>\n";
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Property</th><th>Value</th></tr>\n";
    echo "<tr><td>Type</td><td>{$pricing['pricing_type']}</td></tr>\n";
    echo "<tr><td>Tier</td><td>{$pricing['tier_name']}</td></tr>\n";
    echo "<tr><td>P/A</td><td>£" . number_format($pricing['price_per_annum'], 2) . "</td></tr>\n";
    echo "<tr><td>P/Q</td><td>£" . number_format($pricing['price_per_quarter'], 2) . "</td></tr>\n";
    echo "<tr><td>P/M</td><td>£" . number_format($pricing['price_per_month'], 2) . "</td></tr>\n";
    echo "<tr><td>Payment Frequency</td><td>{$pricing['payment_frequency']}</td></tr>\n";
    echo "<tr><td>Rate to Use</td><td>£" . number_format($pricing['rate_to_use'], 2) . "</td></tr>\n";
    echo "<tr><td><strong>Total Cost</strong></td><td><strong>£" . number_format($pricing['rate_to_use'] * (int)$cameraCount['total'], 2) . "</strong></td></tr>\n";
    echo "</table>\n";
    
    // Test what happens if they add 41 more cameras (total 51)
    echo "<hr>\n";
    echo "<h3>Scenario: If they add 41 cameras (total 51)</h3>\n";
    $newPricing = $pricingService->getPricingForEntity($bm['id'], 51);
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Property</th><th>Value</th></tr>\n";
    echo "<tr><td>New Tier</td><td>{$newPricing['tier_name']}</td></tr>\n";
    echo "<tr><td>New P/A</td><td>£" . number_format($newPricing['price_per_annum'], 2) . "</td></tr>\n";
    echo "<tr><td>Camera Count</td><td>51</td></tr>\n";
    echo "<tr><td><strong>Total Annual Cost</strong></td><td><strong>£" . number_format($newPricing['price_per_annum'] * 51, 2) . "</strong></td></tr>\n";
    echo "</table>\n";
    
    // Test with Frasers (should also be custom)
    echo "<hr>\n";
    $frasers = $db->fetchOne("SELECT id, legal_entity_name FROM legal_entities WHERE legal_entity_name LIKE '%Frasers%'");
    $frasersCameras = $db->fetchOne("
        SELECT COUNT(*) as total
        FROM camera_installations ci
        JOIN stores s ON ci.store_id = s.id
        WHERE s.legal_entity_id = :id
        AND ci.removal_date IS NULL
    ", ['id' => $frasers['id']]);
    
    echo "<h2>Frasers Group</h2>\n";
    echo "<p><strong>Current Cameras:</strong> {$frasersCameras['total']}</p>\n";
    
    $frasersPricing = $pricingService->getPricingForEntity($frasers['id'], (int)$frasersCameras['total']);
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Property</th><th>Value</th></tr>\n";
    echo "<tr><td>Type</td><td>{$frasersPricing['pricing_type']}</td></tr>\n";
    echo "<tr><td>Tier</td><td>{$frasersPricing['tier_name']}</td></tr>\n";
    echo "<tr><td>P/A</td><td>£" . number_format($frasersPricing['price_per_annum'], 2) . "</td></tr>\n";
    echo "<tr><td>Camera Count</td><td>{$frasersCameras['total']}</td></tr>\n";
    echo "<tr><td><strong>Total Annual Cost</strong></td><td><strong>£" . number_format($frasersPricing['price_per_annum'] * (int)$frasersCameras['total'], 2) . "</strong></td></tr>\n";
    echo "</table>\n";
    
    // Test default pricing - find an entity using default pricing or show what it would be
    echo "<hr>\n";
    echo "<h2>Default Pricing Examples</h2>\n";
    echo "<p>What different camera counts would cost using default volume-based pricing:</p>\n";

    $testCounts = [50, 100, 200, 350, 500];
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Cameras</th><th>Tier</th><th>P/A per Camera</th><th>Total Annual Cost</th></tr>\n";

    foreach ($testCounts as $count) {
        // Get default pricing tier for this count
        $tier = $db->fetchOne("
            SELECT * FROM camera_pricing
            WHERE effective_date <= :date
            AND min_cameras <= :count
            AND (max_cameras IS NULL OR max_cameras >= :count2)
            ORDER BY effective_date DESC, min_cameras DESC
            LIMIT 1
        ", ['date' => date('Y-m-d'), 'count' => $count, 'count2' => $count]);

        if ($tier) {
            $tierName = $tier['min_cameras'] . '-' . ($tier['max_cameras'] ?? '∞');
            $total = $tier['price_per_annum'] * $count;
            echo "<tr>";
            echo "<td><strong>{$count}</strong></td>";
            echo "<td>{$tierName}</td>";
            echo "<td>£" . number_format($tier['price_per_annum'], 2) . "</td>";
            echo "<td><strong>£" . number_format($total, 2) . "</strong></td>";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
    
    echo "<hr>\n";
    echo "<h2 style='color: green;'>✓ All tests completed successfully!</h2>\n";
    echo "<p><a href='?page=admin&action=pricing_dashboard'>View Pricing Dashboard →</a></p>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Test failed!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

