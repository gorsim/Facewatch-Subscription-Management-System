<?php
/**
 * Test the Camera Rate System
 * Tests historical rates, volume discounts, and rate updates
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/CameraRateService.php';

use App\Database;
use App\Services\CameraRateService;

echo "<h1>Camera Rate System Test</h1>\n";

try {
    $db = Database::getInstance();
    $rateService = new CameraRateService();
    
    // Get all legal entities
    $entities = $db->fetchAll("SELECT id, legal_entity_name FROM legal_entities ORDER BY legal_entity_name");
    
    echo "<h2>Testing Rate Retrieval for All Legal Entities</h2>\n";
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Entity</th><th>Current Cameras</th><th>Main Rate</th><th>Additional Rate</th><th>Discount Tier</th><th>Discount %</th></tr>\n";
    
    foreach ($entities as $entity) {
        // Get current camera count
        $cameraCount = $db->fetchOne(
            "SELECT COUNT(*) as total
             FROM camera_installations ci
             JOIN stores s ON ci.store_id = s.id
             WHERE s.legal_entity_id = :legal_entity_id
             AND ci.removal_date IS NULL",
            ['legal_entity_id' => $entity['id']]
        );
        $totalCameras = (int) ($cameraCount['total'] ?? 0);
        
        // Get current rates
        $rates = $rateService->getCurrentRates($entity['id']);
        
        echo "<tr>";
        echo "<td>{$entity['legal_entity_name']}</td>";
        echo "<td>{$totalCameras}</td>";
        echo "<td>£" . number_format($rates['main_camera_rate'], 2) . "</td>";
        echo "<td>£" . number_format($rates['additional_camera_rate'], 2) . "</td>";
        echo "<td>" . ($rates['tier_name'] ?? 'N/A') . "</td>";
        echo "<td>" . number_format($rates['discount_percentage'] ?? 0, 2) . "%</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Test volume discount calculation
    echo "<hr>\n";
    echo "<h2>Volume Discount Calculation Test</h2>\n";
    echo "<p>Testing what rates would be for different camera counts:</p>\n";
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Camera Count</th><th>Tier</th><th>Discount %</th><th>Main Rate</th><th>Additional Rate</th><th>Savings vs Standard</th></tr>\n";
    
    $baseRates = $rateService->getBaseRates();
    $testCounts = [50, 100, 150, 200, 300, 500, 750, 1000, 1500];
    
    foreach ($testCounts as $count) {
        $discountedRates = $rateService->calculateDiscountedRates($count);
        $savings = ($baseRates['main_camera_rate'] - $discountedRates['main_rate']) * $count;
        
        echo "<tr>";
        echo "<td>{$count}</td>";
        echo "<td>{$discountedRates['tier_name']}</td>";
        echo "<td>" . number_format($discountedRates['discount_percentage'], 2) . "%</td>";
        echo "<td>£" . number_format($discountedRates['main_rate'], 2) . "</td>";
        echo "<td>£" . number_format($discountedRates['additional_rate'], 2) . "</td>";
        echo "<td style='color: green;'>£" . number_format($savings, 2) . "/year</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Test historical rate lookup
    echo "<hr>\n";
    echo "<h2>Historical Rate Lookup Test</h2>\n";
    echo "<p>Testing rate retrieval for different dates:</p>\n";
    
    if (!empty($entities)) {
        $testEntity = $entities[0];
        echo "<h3>Entity: {$testEntity['legal_entity_name']}</h3>\n";
        
        $testDates = [
            '2024-01-01',
            '2024-06-01',
            '2024-10-01',
            '2025-01-01',
            date('Y-m-d')
        ];
        
        echo "<table border='1' cellpadding='5'>\n";
        echo "<tr><th>Date</th><th>Main Rate</th><th>Additional Rate</th><th>Discount %</th></tr>\n";
        
        foreach ($testDates as $date) {
            $rates = $rateService->getRatesForDate($testEntity['id'], $date);
            echo "<tr>";
            echo "<td>{$date}</td>";
            echo "<td>£" . number_format($rates['main_camera_rate'], 2) . "</td>";
            echo "<td>£" . number_format($rates['additional_camera_rate'], 2) . "</td>";
            echo "<td>" . number_format($rates['discount_percentage'] ?? 0, 2) . "%</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Show rate history
        echo "<h3>Rate History for {$testEntity['legal_entity_name']}</h3>\n";
        $history = $rateService->getRateHistory($testEntity['id']);
        
        if (!empty($history)) {
            echo "<table border='1' cellpadding='5'>\n";
            echo "<tr><th>Effective Date</th><th>End Date</th><th>Main Rate</th><th>Additional Rate</th><th>Cameras</th><th>Discount %</th><th>Reason</th></tr>\n";
            foreach ($history as $h) {
                echo "<tr>";
                echo "<td>{$h['effective_date']}</td>";
                echo "<td>" . ($h['end_date'] ?? 'Current') . "</td>";
                echo "<td>£" . number_format($h['main_camera_rate'], 2) . "</td>";
                echo "<td>£" . number_format($h['additional_camera_rate'], 2) . "</td>";
                echo "<td>{$h['total_cameras']}</td>";
                echo "<td>" . number_format($h['discount_percentage'], 2) . "%</td>";
                echo "<td>{$h['change_reason']}</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<p>No rate history found.</p>\n";
        }
    }
    
    // Test rate update check
    echo "<hr>\n";
    echo "<h2>Rate Update Check</h2>\n";
    echo "<p>Checking if any entities need rate updates due to camera count changes:</p>\n";
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Entity</th><th>Needs Update?</th><th>Current Tier</th><th>Should Be Tier</th><th>Current Cameras</th><th>Actual Cameras</th></tr>\n";
    
    foreach ($entities as $entity) {
        $check = $rateService->checkRateUpdateNeeded($entity['id']);
        $needsUpdate = $check['needs_update'] ? '⚠️ YES' : '✓ No';
        $style = $check['needs_update'] ? 'background-color: #fff3cd;' : '';
        
        echo "<tr style='{$style}'>";
        echo "<td>{$entity['legal_entity_name']}</td>";
        echo "<td>{$needsUpdate}</td>";
        echo "<td>{$check['current_tier']}</td>";
        echo "<td>{$check['new_tier']}</td>";
        echo "<td>{$check['current_cameras']}</td>";
        echo "<td>{$check['actual_cameras']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<hr>\n";
    echo "<h2 style='color: green;'>✓ All tests completed successfully!</h2>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Test failed!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

