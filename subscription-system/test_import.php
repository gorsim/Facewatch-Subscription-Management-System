<?php
/**
 * Test Legal Entity Import with New Pricing System
 */

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Services\SubscriberImporter;
use App\Database;

echo "=== Testing Legal Entity Import (New Pricing System) ===\n\n";

// Test file path
$testFile = __DIR__ . '/test_data/legal_entities_import_sample.csv';

if (!file_exists($testFile)) {
    echo "❌ Test file not found: {$testFile}\n";
    exit(1);
}

echo "📄 Test file: {$testFile}\n\n";

// Show CSV contents
echo "CSV Contents:\n";
echo "---\n";
echo file_get_contents($testFile);
echo "---\n\n";

// Run import
$importer = new SubscriberImporter();
echo "🔄 Running import...\n\n";

$success = $importer->import($testFile);

if ($success) {
    echo "✅ Import successful!\n";
    echo "   - Imported: {$importer->getImported()} new entities\n";
    echo "   - Updated: {$importer->getUpdated()} existing entities\n\n";
} else {
    echo "❌ Import failed!\n";
    $errors = $importer->getErrors();
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    echo "\n";
}

// Verify imported data
echo "=== Verifying Imported Data ===\n\n";

$db = Database::getInstance();
$entities = $db->fetchAll("
    SELECT 
        legal_entity_id,
        legal_entity_name,
        xero_company_name,
        payment_frequency,
        pricing_type,
        installation_date,
        category,
        sales_credit
    FROM legal_entities 
    WHERE legal_entity_id LIKE 'TEST%'
    ORDER BY legal_entity_id
");

if (empty($entities)) {
    echo "⚠️ No test entities found in database\n";
} else {
    foreach ($entities as $entity) {
        echo "Entity: {$entity['legal_entity_name']}\n";
        echo "  ID: {$entity['legal_entity_id']}\n";
        echo "  Xero Name: {$entity['xero_company_name']}\n";
        echo "  Payment Frequency: {$entity['payment_frequency']}\n";
        echo "  Pricing Type: {$entity['pricing_type']}\n";
        echo "  Installation Date: {$entity['installation_date']}\n";
        echo "  Category: {$entity['category']}\n";
        echo "  Sales Credit: {$entity['sales_credit']}\n";
        echo "\n";
    }
}

echo "=== Test Complete ===\n";

