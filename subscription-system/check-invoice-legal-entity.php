<?php
// Direct MySQL connection for command-line script
$mysqli = new mysqli('localhost', 'root', 'root', 'facewatch_subscriptions');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "🔍 Checking Invoice and Legal Entity Data\n";
echo "==========================================\n\n";

// Check all legal entities
echo "Legal Entities in Database:\n";
$result = $mysqli->query("SELECT * FROM legal_entities ORDER BY id");
while ($le = $result->fetch_assoc()) {
    echo "  ID {$le['id']}: {$le['legal_entity_name']} (Xero: {$le['xero_company_name']})\n";
}
echo "\n";

// Check the invoice
echo "Invoice FW104 Details:\n";
$result = $mysqli->query("SELECT * FROM invoices WHERE invoice_number = 'FW104'");
$invoice = $result->fetch_assoc();
if ($invoice) {
    echo "  Invoice ID: {$invoice['id']}\n";
    echo "  Invoice Number: {$invoice['invoice_number']}\n";
    echo "  Legal Entity ID: {$invoice['legal_entity_id']}\n";
    echo "  Invoice Date: {$invoice['invoice_date']}\n";
    echo "  Amount: £{$invoice['invoice_amount']}\n";
    
    // Check if this legal_entity_id exists
    $result2 = $mysqli->query("SELECT * FROM legal_entities WHERE id = {$invoice['legal_entity_id']}");
    $le = $result2->fetch_assoc();
    if ($le) {
        echo "  Legal Entity Name: {$le['legal_entity_name']}\n";
    } else {
        echo "  ⚠️  WARNING: legal_entity_id {$invoice['legal_entity_id']} does NOT exist in legal_entities table!\n";
    }
} else {
    echo "  ❌ Invoice FW104 not found!\n";
}
echo "\n";

// Check Store 10
echo "Store 10 Details:\n";
$result = $mysqli->query("SELECT * FROM stores WHERE store_id = '10'");
$store = $result->fetch_assoc();
if ($store) {
    echo "  Store ID: {$store['id']}\n";
    echo "  Store Name: {$store['store_name']}\n";
    echo "  Legal Entity ID: {$store['legal_entity_id']}\n";
    
    $result2 = $mysqli->query("SELECT * FROM legal_entities WHERE id = {$store['legal_entity_id']}");
    $le = $result2->fetch_assoc();
    if ($le) {
        echo "  Legal Entity Name: {$le['legal_entity_name']}\n";
    }
}
echo "\n";

echo "🔍 DIAGNOSIS:\n";
echo "The invoice is looking for legal_entity_id = {$invoice['legal_entity_id']}\n";
echo "Store 10 is linked to legal_entity_id = {$store['legal_entity_id']}\n";
echo "We need to fix the INVOICE to use legal_entity_id = 10 (Frasers Group)\n";

$mysqli->close();
?>

