<?php
/**
 * Setup Pricing Data
 * 
 * This script:
 * 1. Adds default volume-based pricing tiers (camera_pricing table)
 * 2. Shows which legal entities are using which pricing model
 * 3. Allows you to switch entities to independent pricing model
 */

require_once __DIR__ . '/../app/Database.php';

$db = App\Database::getInstance();

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║              PRICING DATA SETUP                            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    // Step 1: Add default volume-based pricing (camera_pricing table)
    echo "Step 1: Adding default volume-based pricing tiers...\n";
    echo "─────────────────────────────────────────────────────────────\n";
    
    $volumePricing = [
        ['min' => 1, 'max' => 5, 'annual' => 500.00, 'quarterly' => 135.00, 'monthly' => 50.00],
        ['min' => 6, 'max' => 10, 'annual' => 450.00, 'quarterly' => 120.00, 'monthly' => 45.00],
        ['min' => 11, 'max' => 20, 'annual' => 400.00, 'quarterly' => 110.00, 'monthly' => 40.00],
        ['min' => 21, 'max' => 999, 'annual' => 350.00, 'quarterly' => 95.00, 'monthly' => 35.00]
    ];
    
    // Clear existing volume pricing
    $db->query("DELETE FROM camera_pricing");
    
    foreach ($volumePricing as $tier) {
        $db->query("
            INSERT INTO camera_pricing 
            (min_cameras, max_cameras, price_per_annum, price_per_quarter, price_per_month, effective_date, notes)
            VALUES (:min, :max, :annual, :quarterly, :monthly, '2020-01-01', 'Default volume-based pricing')
        ", [
            'min' => $tier['min'],
            'max' => $tier['max'],
            'annual' => $tier['annual'],
            'quarterly' => $tier['quarterly'],
            'monthly' => $tier['monthly']
        ]);
        echo "  ✅ Added tier: {$tier['min']}-{$tier['max']} cameras @ £{$tier['annual']}/year\n";
    }
    
    // Step 2: Show current legal entity pricing models
    echo "\nStep 2: Current Legal Entity Pricing Models:\n";
    echo "─────────────────────────────────────────────────────────────\n";
    
    $entities = $db->fetchAll("
        SELECT id, legal_entity_name, pricing_type, pricing_model, payment_frequency
        FROM legal_entities
        ORDER BY legal_entity_name
        LIMIT 20
    ");
    
    $volumeCount = 0;
    $independentCount = 0;
    $customCount = 0;
    
    foreach ($entities as $entity) {
        $model = $entity['pricing_model'] ?? 'volume_based';
        $type = $entity['pricing_type'] ?? 'default';
        
        if ($model === 'first_plus_additional') {
            $independentCount++;
            echo "  🔵 {$entity['legal_entity_name']}: INDEPENDENT (first + additional)\n";
        } elseif ($type === 'custom') {
            $customCount++;
            echo "  🟡 {$entity['legal_entity_name']}: CUSTOM\n";
        } else {
            $volumeCount++;
            echo "  🟢 {$entity['legal_entity_name']}: VOLUME-BASED\n";
        }
    }
    
    echo "\nSummary:\n";
    echo "  Volume-based: $volumeCount\n";
    echo "  Independent: $independentCount\n";
    echo "  Custom: $customCount\n";
    
    // Step 3: Instructions
    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║                  ✅ SETUP COMPLETE!                        ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📋 What's Available Now:\n\n";
    echo "1. VOLUME-BASED PRICING (camera_pricing table):\n";
    echo "   - 1-5 cameras: £500/year, £135/quarter, £50/month\n";
    echo "   - 6-10 cameras: £450/year, £120/quarter, £45/month\n";
    echo "   - 11-20 cameras: £400/year, £110/quarter, £40/month\n";
    echo "   - 21+ cameras: £350/year, £95/quarter, £35/month\n\n";
    
    echo "2. INDEPENDENT PRICING (default_independent_pricing table):\n";
    echo "   - Already has data for years 2020-2031\n";
    echo "   - First camera + additional camera model\n\n";
    
    echo "🔧 To Switch an Entity to Independent Pricing:\n";
    echo "   Run this SQL in the database:\n";
    echo "   UPDATE legal_entities \n";
    echo "   SET pricing_model = 'first_plus_additional' \n";
    echo "   WHERE legal_entity_name = 'Your Entity Name';\n\n";
    
    echo "✅ Now you can allocate cameras and the system will calculate amounts!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

