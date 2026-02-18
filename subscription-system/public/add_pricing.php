<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();

echo "<h2>Adding Default Pricing Tiers</h2>";

// Define pricing tiers
$pricingTiers = [
    // 1-5 cameras
    [
        'camera_count_min' => 1,
        'camera_count_max' => 5,
        'annual_price_per_camera' => 500.00,
        'quarterly_price_per_camera' => 135.00,
        'monthly_price_per_camera' => 50.00,
        'effective_from' => '2020-01-01',
        'effective_to' => null
    ],
    // 6-10 cameras
    [
        'camera_count_min' => 6,
        'camera_count_max' => 10,
        'annual_price_per_camera' => 450.00,
        'quarterly_price_per_camera' => 120.00,
        'monthly_price_per_camera' => 45.00,
        'effective_from' => '2020-01-01',
        'effective_to' => null
    ],
    // 11-20 cameras
    [
        'camera_count_min' => 11,
        'camera_count_max' => 20,
        'annual_price_per_camera' => 400.00,
        'quarterly_price_per_camera' => 110.00,
        'monthly_price_per_camera' => 40.00,
        'effective_from' => '2020-01-01',
        'effective_to' => null
    ],
    // 21+ cameras
    [
        'camera_count_min' => 21,
        'camera_count_max' => 999,
        'annual_price_per_camera' => 350.00,
        'quarterly_price_per_camera' => 95.00,
        'monthly_price_per_camera' => 35.00,
        'effective_from' => '2020-01-01',
        'effective_to' => null
    ]
];

try {
    $stmt = $db->prepare("
        INSERT INTO pricing_tiers 
        (camera_count_min, camera_count_max, annual_price_per_camera, quarterly_price_per_camera, 
         monthly_price_per_camera, effective_from, effective_to)
        VALUES 
        (:camera_count_min, :camera_count_max, :annual_price_per_camera, :quarterly_price_per_camera,
         :monthly_price_per_camera, :effective_from, :effective_to)
    ");
    
    foreach ($pricingTiers as $tier) {
        $stmt->execute($tier);
        echo "<p>✅ Added pricing tier: {$tier['camera_count_min']}-{$tier['camera_count_max']} cameras @ £{$tier['annual_price_per_camera']}/year</p>";
    }
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✅ All pricing tiers added successfully!</h3>";
    echo "<p><a href='check_pricing.php'>View Pricing Tiers</a></p>";
    echo "<p><a href='?page=invoices&action=create&legal_entity_id=1'>Back to Create Invoice</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

