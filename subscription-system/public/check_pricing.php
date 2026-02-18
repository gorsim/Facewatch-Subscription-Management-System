<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();

echo "<h2>Pricing Tiers in Database</h2>";

$stmt = $db->query("SELECT * FROM pricing_tiers ORDER BY effective_from, camera_count_min");
$tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tiers)) {
    echo "<p style='color: red; font-weight: bold;'>❌ NO PRICING TIERS FOUND!</p>";
    echo "<p>The pricing_tiers table is empty. You need to add pricing data.</p>";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background: #eee;'>";
    echo "<th>ID</th><th>Camera Range</th><th>Annual Price</th><th>Quarterly Price</th><th>Monthly Price</th><th>Effective From</th><th>Effective To</th>";
    echo "</tr>";
    
    foreach ($tiers as $tier) {
        echo "<tr>";
        echo "<td>{$tier['id']}</td>";
        echo "<td>{$tier['camera_count_min']} - {$tier['camera_count_max']}</td>";
        echo "<td>£" . number_format($tier['annual_price_per_camera'], 2) . "</td>";
        echo "<td>£" . number_format($tier['quarterly_price_per_camera'], 2) . "</td>";
        echo "<td>£" . number_format($tier['monthly_price_per_camera'], 2) . "</td>";
        echo "<td>{$tier['effective_from']}</td>";
        echo "<td>" . ($tier['effective_to'] ?? 'Current') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<hr>";
echo "<h3>Test: What pricing would be used for 2 cameras on 2025-05-17?</h3>";

$testDate = '2025-05-17';
$testCameras = 2;

$stmt = $db->prepare("
    SELECT * FROM pricing_tiers 
    WHERE camera_count_min <= :camera_count 
    AND camera_count_max >= :camera_count
    AND effective_from <= :invoice_date
    AND (effective_to IS NULL OR effective_to >= :invoice_date)
    ORDER BY effective_from DESC
    LIMIT 1
");

$stmt->execute([
    'camera_count' => $testCameras,
    'invoice_date' => $testDate
]);

$pricing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pricing) {
    echo "<p style='color: green;'>✅ Found pricing tier: ID {$pricing['id']}</p>";
    echo "<p>Annual: £" . number_format($pricing['annual_price_per_camera'], 2) . " per camera</p>";
} else {
    echo "<p style='color: red;'>❌ No pricing tier found for {$testCameras} cameras on {$testDate}</p>";
    echo "<p>This is why the invoice creation is failing!</p>";
}

