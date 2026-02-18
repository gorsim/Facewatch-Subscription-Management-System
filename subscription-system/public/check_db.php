<?php
/**
 * Quick database check
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "<h1>Database Check</h1>";
    
    // Check tables
    echo "<h2>Tables</h2>";
    $tables = $db->fetchAll("SHOW TABLES");
    echo "<pre>";
    print_r($tables);
    echo "</pre>";
    
    // Check invoice count
    echo "<h2>Invoice Count</h2>";
    $invoiceCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoices");
    echo "Total invoices: " . ($invoiceCount['count'] ?? 0) . "<br>";
    
    // Check legal entity count
    echo "<h2>Legal Entity Count</h2>";
    $entityCount = $db->fetchOne("SELECT COUNT(*) as count FROM legal_entities");
    echo "Total legal entities: " . ($entityCount['count'] ?? 0) . "<br>";
    
    // Check camera count
    echo "<h2>Camera Installation Count</h2>";
    $cameraCount = $db->fetchOne("SELECT COUNT(*) as count FROM camera_installations");
    echo "Total cameras: " . ($cameraCount['count'] ?? 0) . "<br>";
    
    // Check recent invoices
    echo "<h2>Recent Invoices (Last 10)</h2>";
    $recentInvoices = $db->fetchAll("
        SELECT i.invoice_number, i.invoice_date, i.invoice_amount, le.legal_entity_name
        FROM invoices i
        LEFT JOIN legal_entities le ON i.legal_entity_id = le.id
        ORDER BY i.invoice_date DESC
        LIMIT 10
    ");
    echo "<pre>";
    print_r($recentInvoices);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}

