<?php
/**
 * Test Script for Smart Matching
 * This simulates uploading the test CSV and shows the matching results
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/InvoiceMatchingService.php';

use App\Database;
use App\Services\InvoiceMatchingService;

$db = Database::getInstance();
$matchingService = new InvoiceMatchingService();

echo "🧪 SMART MATCHING TEST\n";
echo str_repeat("=", 80) . "\n\n";

// Step 1: Create some test invoices in the system
echo "Step 1: Creating test invoices in the system...\n";
echo str_repeat("-", 80) . "\n";

// First, check if we have legal entities
$entities = $db->fetchAll("SELECT * FROM legal_entities LIMIT 5");

if (empty($entities)) {
    echo "❌ No legal entities found! Creating test entities...\n";
    
    $testEntities = [
        ['Tesco Stores Ltd', 'Tesco'],
        ['Sainsburys Supermarkets', 'Sainsburys'],
        ['Marks and Spencer PLC', 'M&S'],
        ['Waitrose Limited', 'Waitrose'],
        ['Co-operative Group', 'Co-op']
    ];
    
    foreach ($testEntities as $entity) {
        $db->insert('legal_entities', [
            'legal_entity_name' => $entity[0],
            'xero_company_name' => $entity[1],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "  ✅ Created: {$entity[0]}\n";
    }
    
    $entities = $db->fetchAll("SELECT * FROM legal_entities LIMIT 5");
}

echo "\n✅ Found " . count($entities) . " legal entities\n\n";

// Create test invoices if they don't exist
echo "Creating test invoices with 'issued' status...\n";

$testInvoices = [
    ['entity' => 'Tesco Stores Ltd', 'date' => '2026-02-01', 'amount' => 1250.00],
    ['entity' => 'Sainsburys Supermarkets', 'date' => '2026-02-05', 'amount' => 890.50],
    ['entity' => 'Marks and Spencer PLC', 'date' => '2026-02-10', 'amount' => 1500.00],
    ['entity' => 'Waitrose Limited', 'date' => '2026-02-12', 'amount' => 750.00],
    ['entity' => 'Co-operative Group', 'date' => '2026-02-15', 'amount' => 950.00],
];

foreach ($testInvoices as $inv) {
    // Find the legal entity
    $entity = $db->fetchOne("SELECT * FROM legal_entities WHERE legal_entity_name = :name", 
        ['name' => $inv['entity']]);
    
    if ($entity) {
        // Check if invoice already exists
        $existing = $db->fetchOne("
            SELECT * FROM invoices 
            WHERE legal_entity_id = :entity_id 
            AND invoice_date = :date 
            AND invoice_amount = :amount
        ", [
            'entity_id' => $entity['id'],
            'date' => $inv['date'],
            'amount' => $inv['amount']
        ]);
        
        if (!$existing) {
            $invoiceNumber = 'INV-TEST-' . date('Ymd') . '-' . rand(1000, 9999);
            $db->insert('invoices', [
                'legal_entity_id' => $entity['id'],
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $inv['date'],
                'invoice_amount' => $inv['amount'],
                'invoice_status' => 'issued',
                'payment_status' => 'unpaid',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            echo "  ✅ Created invoice: {$invoiceNumber} for {$inv['entity']}\n";
        } else {
            echo "  ℹ️  Invoice already exists for {$inv['entity']}\n";
        }
    }
}

echo "\n";

// Step 2: Create import session
echo "Step 2: Creating import session...\n";
echo str_repeat("-", 80) . "\n";

$db->insert('xero_import_sessions', [
    'session_name' => 'Test Import - ' . date('Y-m-d H:i:s'),
    'imported_by' => 'test_script',
    'status' => 'pending'
]);
$sessionId = $db->getConnection()->lastInsertId();
echo "✅ Created session ID: $sessionId\n\n";

// Step 3: Import test CSV data
echo "Step 3: Importing Xero invoices from test CSV...\n";
echo str_repeat("-", 80) . "\n";

$csvFile = __DIR__ . '/test_xero_import.csv';
if (!file_exists($csvFile)) {
    echo "❌ Test CSV file not found: $csvFile\n";
    exit(1);
}

$handle = fopen($csvFile, 'r');
$header = fgetcsv($handle);
$imported = 0;

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 5) continue;
    
    $data = array_combine($header, $row);
    
    $db->insert('xero_imported_invoices', [
        'session_id' => $sessionId,
        'xero_invoice_id' => $data['Invoice ID'],
        'xero_invoice_number' => $data['Invoice Number'],
        'contact_name' => $data['Contact Name'],
        'invoice_date' => date('Y-m-d', strtotime($data['Date'])),
        'due_date' => null,
        'amount' => floatval($data['Amount Due']),
        'status' => $data['Status']
    ]);
    
    echo "  ✅ Imported: {$data['Invoice Number']} - {$data['Contact Name']} - £{$data['Amount Due']}\n";
    $imported++;
}

fclose($handle);

// Update session
$db->update('xero_import_sessions', [
    'total_invoices' => $imported,
    'status' => 'reviewing'
], 'id = :id', ['id' => $sessionId]);

echo "\n✅ Imported $imported invoices\n\n";

// Step 4: Run matching algorithm
echo "Step 4: Running smart matching algorithm...\n";
echo str_repeat("-", 80) . "\n";

$matches = $matchingService->findMatches($sessionId);

echo "\n📊 MATCHING RESULTS:\n";
echo str_repeat("=", 80) . "\n\n";

// Display perfect matches
if (!empty($matches['perfect']) || !empty($matches['auto'])) {
    $perfectMatches = array_merge($matches['perfect'], $matches['auto']);
    echo "✅ PERFECT MATCHES (" . count($perfectMatches) . "):\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($perfectMatches as $match) {
        echo "  Xero: {$match['xero_invoice']['xero_invoice_number']} - {$match['xero_invoice']['contact_name']}\n";
        echo "  System: {$match['system_invoice']['invoice_number']} - {$match['system_invoice']['legal_entity_name']}\n";
        echo "  Score: {$match['score']}% 🎯\n";
        echo "  Breakdown:\n";
        echo "    - Entity: {$match['breakdown']['entity']['match']} ({$match['breakdown']['entity']['score']} pts)\n";
        echo "    - Date: {$match['breakdown']['date']['match']} ({$match['breakdown']['date']['score']} pts)\n";
        echo "    - Amount: {$match['breakdown']['amount']['match']} ({$match['breakdown']['amount']['score']} pts)\n";
        echo "\n";
    }
}

// Display suggested matches
if (!empty($matches['suggested'])) {
    echo "💡 SUGGESTED MATCHES (" . count($matches['suggested']) . "):\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($matches['suggested'] as $match) {
        echo "  Xero: {$match['xero_invoice']['xero_invoice_number']} - {$match['xero_invoice']['contact_name']}\n";
        echo "  System: {$match['system_invoice']['invoice_number']} - {$match['system_invoice']['legal_entity_name']}\n";
        echo "  Score: {$match['score']}% 🟡\n";
        echo "\n";
    }
}

// Display possible matches
if (!empty($matches['possible'])) {
    echo "🤔 POSSIBLE MATCHES (" . count($matches['possible']) . "):\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($matches['possible'] as $match) {
        echo "  Xero: {$match['xero_invoice']['xero_invoice_number']} - {$match['xero_invoice']['contact_name']}\n";
        echo "  System: {$match['system_invoice']['invoice_number']} - {$match['system_invoice']['legal_entity_name']}\n";
        echo "  Score: {$match['score']}% 🟠\n";
        echo "\n";
    }
}

// Display unmatched
if (!empty($matches['unmatched'])) {
    echo "❌ UNMATCHED INVOICES (" . count($matches['unmatched']) . "):\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($matches['unmatched'] as $item) {
        echo "  Xero: {$item['xero_invoice']['xero_invoice_number']} - {$item['xero_invoice']['contact_name']}\n";
        echo "  Amount: £{$item['xero_invoice']['amount']}\n";
        echo "  Date: {$item['xero_invoice']['invoice_date']}\n";
        echo "\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "✅ TEST COMPLETE!\n\n";
echo "View results in browser:\n";
echo "http://localhost:8080/subscription-system/public/?page=invoices&action=smart_match&session=$sessionId\n\n";

