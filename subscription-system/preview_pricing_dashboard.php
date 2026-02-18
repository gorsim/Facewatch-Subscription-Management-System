<?php
/**
 * Preview Pricing Dashboard Data
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/PricingService.php';

use App\Database;
use App\Services\PricingService;

echo "<h1>📊 Pricing Tiers Dashboard Preview</h1>\n";
echo "<p style='color: #666;'>As of " . date('d M Y') . "</p>\n";

try {
    $db = Database::getInstance();
    $pricingService = new PricingService();
    
    // Get all legal entities with their camera counts
    $entities = $db->fetchAll("
        SELECT 
            le.id,
            le.legal_entity_name,
            le.pricing_type,
            le.payment_frequency,
            COUNT(DISTINCT ci.id) as camera_count
        FROM legal_entities le
        LEFT JOIN stores s ON le.id = s.legal_entity_id
        LEFT JOIN camera_installations ci ON s.id = ci.store_id AND ci.removal_date IS NULL
        GROUP BY le.id, le.legal_entity_name, le.pricing_type, le.payment_frequency
        ORDER BY le.legal_entity_name
    ");
    
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<thead style='background: #f8f9fa;'>\n";
    echo "<tr>\n";
    echo "<th>Legal Entity</th>\n";
    echo "<th>Cameras</th>\n";
    echo "<th>Pricing Type</th>\n";
    echo "<th>Current Tier</th>\n";
    echo "<th>Rate (P/A)</th>\n";
    echo "<th>Rate (P/Q)</th>\n";
    echo "<th>Rate (P/M)</th>\n";
    echo "<th>Payment Freq</th>\n";
    echo "<th>Total Cost</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    
    $totalCameras = 0;
    $customCount = 0;
    $defaultCount = 0;
    
    foreach ($entities as $entity) {
        $totalCameras += (int) $entity['camera_count'];
        
        if ($entity['pricing_type'] === 'custom') {
            $customCount++;
        } else {
            $defaultCount++;
        }
        
        try {
            $pricing = $pricingService->getPricingForEntity(
                $entity['id'],
                (int) $entity['camera_count'],
                date('Y-m-d')
            );
            
            $freq = $entity['payment_frequency'] ?? 'annual';
            $cost = $freq === 'monthly' ? ($pricing['price_per_month'] * (int) $entity['camera_count']) : 
                   ($freq === 'quarterly' ? ($pricing['price_per_quarter'] * (int) $entity['camera_count']) : 
                   ($pricing['price_per_annum'] * (int) $entity['camera_count']));
            $period = $freq === 'monthly' ? '/mo' : ($freq === 'quarterly' ? '/qtr' : '/yr');
            
            $pricingBadge = $entity['pricing_type'] === 'custom' 
                ? "<span style='background: #fff3cd; padding: 3px 8px; border-radius: 3px;'>🎯 Custom</span>"
                : "<span style='background: #d1ecf1; padding: 3px 8px; border-radius: 3px;'>📊 Default</span>";
            
            echo "<tr>\n";
            echo "<td><strong>" . htmlspecialchars($entity['legal_entity_name']) . "</strong></td>\n";
            echo "<td style='text-align: center;'><strong>{$entity['camera_count']}</strong></td>\n";
            echo "<td>{$pricingBadge}</td>\n";
            echo "<td><strong>{$pricing['tier_name']}</strong></td>\n";
            echo "<td>£" . number_format($pricing['price_per_annum'], 2) . "</td>\n";
            echo "<td>£" . number_format($pricing['price_per_quarter'], 2) . "</td>\n";
            echo "<td>£" . number_format($pricing['price_per_month'], 2) . "</td>\n";
            echo "<td style='text-align: center;'><strong>" . ucfirst($freq) . "</strong></td>\n";
            echo "<td><strong style='color: #28a745;'>£" . number_format($cost, 2) . "{$period}</strong></td>\n";
            echo "</tr>\n";
            
        } catch (Exception $e) {
            echo "<tr>\n";
            echo "<td><strong>" . htmlspecialchars($entity['legal_entity_name']) . "</strong></td>\n";
            echo "<td style='text-align: center;'>{$entity['camera_count']}</td>\n";
            echo "<td colspan='7' style='color: #dc3545;'>Error: " . htmlspecialchars($e->getMessage()) . "</td>\n";
            echo "</tr>\n";
        }
    }
    
    echo "</tbody>\n";
    echo "</table>\n";
    
    // Summary
    echo "<hr>\n";
    echo "<h2>Summary Statistics</h2>\n";
    echo "<div style='display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px;'>\n";
    
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>\n";
    echo "<h3 style='margin: 0; color: #666; font-size: 14px;'>Total Entities</h3>\n";
    echo "<p style='font-size: 32px; margin: 10px 0; font-weight: bold;'>" . count($entities) . "</p>\n";
    echo "</div>\n";
    
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>\n";
    echo "<h3 style='margin: 0; color: #666; font-size: 14px;'>Total Cameras</h3>\n";
    echo "<p style='font-size: 32px; margin: 10px 0; font-weight: bold;'>" . $totalCameras . "</p>\n";
    echo "</div>\n";
    
    echo "<div style='background: #fff3cd; padding: 20px; border-radius: 8px;'>\n";
    echo "<h3 style='margin: 0; color: #666; font-size: 14px;'>Custom Pricing</h3>\n";
    echo "<p style='font-size: 32px; margin: 10px 0; font-weight: bold;'>🎯 " . $customCount . "</p>\n";
    echo "</div>\n";
    
    echo "<div style='background: #d1ecf1; padding: 20px; border-radius: 8px;'>\n";
    echo "<h3 style='margin: 0; color: #666; font-size: 14px;'>Default Pricing</h3>\n";
    echo "<p style='font-size: 32px; margin: 10px 0; font-weight: bold;'>📊 " . $defaultCount . "</p>\n";
    echo "</div>\n";
    
    echo "</div>\n";
    
    echo "<hr>\n";
    echo "<h2 style='color: green;'>✓ Dashboard preview generated successfully!</h2>\n";
    echo "<p><strong>Access the live dashboard at:</strong> <a href='http://localhost:8888/subscription-system/public/?page=admin&action=pricing_dashboard'>Pricing Tiers Dashboard</a></p>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Error generating preview!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

