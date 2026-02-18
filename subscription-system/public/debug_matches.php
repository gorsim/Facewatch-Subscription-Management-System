<?php
/**
 * Debug Matching Results
 * Shows what's in the database for the latest import session
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = new Database();

echo "<h1>Debug: Latest Import Session Matches</h1>";

// Get latest session
$session = $db->query(
    "SELECT * FROM xero_import_sessions ORDER BY id DESC LIMIT 1"
)->fetch();

if (!$session) {
    echo "<p>No import sessions found</p>";
    exit;
}

echo "<h2>Session: {$session['session_name']}</h2>";
echo "<p>Status: {$session['status']}</p>";
echo "<p>Total: {$session['total_invoices']} | Matched: {$session['matched_count']} | Reconciled: {$session['reconciled_count']}</p>";

// Get all Xero invoices in this session
$xeroInvoices = $db->query(
    "SELECT 
        xii.id,
        xii.xero_invoice_number,
        xii.contact_name,
        xii.invoice_date,
        xii.total_amount,
        xii.match_status,
        xii.matched_invoice_id,
        xii.match_score,
        xii.match_breakdown,
        i.invoice_number as matched_system_invoice,
        i.invoice_amount as matched_system_amount
    FROM xero_imported_invoices xii
    LEFT JOIN invoices i ON xii.matched_invoice_id = i.id
    WHERE xii.session_id = :session_id
    ORDER BY xii.match_score DESC",
    ['session_id' => $session['id']]
)->fetchAll();

echo "<h2>Xero Invoices (" . count($xeroInvoices) . " total)</h2>";

foreach ($xeroInvoices as $invoice) {
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0; background: #f9f9f9;'>";
    echo "<h3>{$invoice['xero_invoice_number']} - {$invoice['contact_name']}</h3>";
    echo "<p><strong>Date:</strong> {$invoice['invoice_date']} | <strong>Amount:</strong> £" . number_format($invoice['total_amount'], 2) . "</p>";
    echo "<p><strong>Match Status:</strong> {$invoice['match_status']} | <strong>Score:</strong> {$invoice['match_score']}%</p>";
    
    if ($invoice['matched_invoice_id']) {
        echo "<p><strong>Matched to:</strong> {$invoice['matched_system_invoice']} (£" . number_format($invoice['matched_system_amount'], 2) . ")</p>";
    } else {
        echo "<p><strong>Matched to:</strong> None</p>";
    }
    
    if ($invoice['match_breakdown']) {
        echo "<p><strong>Breakdown:</strong></p>";
        echo "<pre>" . print_r(json_decode($invoice['match_breakdown'], true), true) . "</pre>";
    }
    
    echo "</div>";
}

// Get all system invoices that could match
echo "<h2>Available System Invoices</h2>";

$systemInvoices = $db->query(
    "SELECT 
        i.id,
        i.invoice_number,
        le.entity_name,
        i.invoice_date,
        i.invoice_amount,
        i.status
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE i.status IN ('draft', 'issued')
    ORDER BY i.invoice_date DESC"
)->fetchAll();

echo "<p>Found " . count($systemInvoices) . " system invoices</p>";

foreach ($systemInvoices as $invoice) {
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0; background: #fff;'>";
    echo "<p><strong>{$invoice['invoice_number']}</strong> - {$invoice['entity_name']}</p>";
    echo "<p>Date: {$invoice['invoice_date']} | Amount: £" . number_format($invoice['invoice_amount'], 2) . " | Status: {$invoice['status']}</p>";
    echo "</div>";
}

