<?php
/**
 * Debug Smart Match - Check why invoices aren't matching
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/InvoiceMatchingService.php';

use App\Database;
use App\Services\InvoiceMatchingService;

$db = Database::getInstance();
$matchingService = new InvoiceMatchingService();

echo "=== SMART MATCH DEBUG ===\n\n";

// 1. Check for Brookfield entity
echo "1. Checking for Brookfield legal entity:\n";
$brookfield = $db->fetchAll("
    SELECT id, legal_entity_name, xero_company_name 
    FROM legal_entities 
    WHERE legal_entity_name LIKE '%Brookfield%' 
    OR xero_company_name LIKE '%Brookfield%'
");

if (empty($brookfield)) {
    echo "   ❌ No Brookfield entity found!\n\n";
} else {
    foreach ($brookfield as $entity) {
        echo "   ✅ Found: ID={$entity['id']}, Name={$entity['legal_entity_name']}, Xero Name={$entity['xero_company_name']}\n";
        
        // Check for invoices
        $invoices = $db->fetchAll("
            SELECT id, invoice_number, invoice_date, invoice_amount, invoice_status, is_forecast
            FROM invoices
            WHERE legal_entity_id = :id
            AND invoice_status = 'issued'
            AND is_forecast = 0
            ORDER BY invoice_date DESC
            LIMIT 5
        ", ['id' => $entity['id']]);
        
        echo "   Invoices for this entity:\n";
        if (empty($invoices)) {
            echo "      ❌ No issued invoices found\n";
        } else {
            foreach ($invoices as $inv) {
                echo "      - #{$inv['invoice_number']}: £{$inv['invoice_amount']} on {$inv['invoice_date']} (status: {$inv['invoice_status']})\n";
            }
        }
        echo "\n";
    }
}

// 2. Check recent Xero import sessions
echo "2. Recent Xero import sessions:\n";
$sessions = $db->fetchAll("
    SELECT id, import_date, status, invoice_count
    FROM xero_import_sessions
    ORDER BY import_date DESC
    LIMIT 5
");

if (empty($sessions)) {
    echo "   ❌ No import sessions found\n\n";
} else {
    foreach ($sessions as $session) {
        echo "   Session #{$session['id']}: {$session['import_date']} ({$session['invoice_count']} invoices) - {$session['status']}\n";
    }
    echo "\n";
    
    // Get the most recent session
    $latestSession = $sessions[0];
    echo "3. Checking latest session #{$latestSession['id']}:\n";
    
    $xeroInvoices = $db->fetchAll("
        SELECT id, xero_invoice_number, contact_name, invoice_date, total_amount, match_status, matched_invoice_id
        FROM xero_imported_invoices
        WHERE session_id = :session_id
        ORDER BY invoice_date DESC
        LIMIT 10
    ", ['session_id' => $latestSession['id']]);
    
    echo "   Xero invoices in this session:\n";
    foreach ($xeroInvoices as $xi) {
        $matchInfo = $xi['matched_invoice_id'] ? " → Matched to #{$xi['matched_invoice_id']}" : " (unmatched)";
        echo "      - {$xi['xero_invoice_number']}: {$xi['contact_name']} - £{$xi['total_amount']} on {$xi['invoice_date']} [{$xi['match_status']}]{$matchInfo}\n";
    }
    echo "\n";
    
    // 4. Test matching for Brookfield invoices
    if (!empty($brookfield) && !empty($xeroInvoices)) {
        echo "4. Testing match scores for Brookfield:\n";
        
        // Find a Brookfield Xero invoice
        $brookfieldXero = null;
        foreach ($xeroInvoices as $xi) {
            if (stripos($xi['contact_name'], 'Brookfield') !== false) {
                $brookfieldXero = $xi;
                break;
            }
        }
        
        if ($brookfieldXero) {
            echo "   Found Xero invoice: {$brookfieldXero['xero_invoice_number']} - {$brookfieldXero['contact_name']} - £{$brookfieldXero['total_amount']}\n";
            
            // Get system invoices for Brookfield
            $systemInvoices = $db->fetchAll("
                SELECT i.*, le.legal_entity_name, le.xero_company_name
                FROM invoices i
                JOIN legal_entities le ON i.legal_entity_id = le.id
                WHERE le.id = :entity_id
                AND i.invoice_status = 'issued'
                AND i.is_forecast = 0
                ORDER BY i.invoice_date DESC
                LIMIT 5
            ", ['entity_id' => $brookfield[0]['id']]);
            
            echo "   Testing against system invoices:\n";
            foreach ($systemInvoices as $sysInv) {
                $matchResult = $matchingService->calculateMatchScore($sysInv, $brookfieldXero);
                echo "      - #{$sysInv['invoice_number']}: £{$sysInv['invoice_amount']} on {$sysInv['invoice_date']}\n";
                echo "        Score: {$matchResult['total_score']}/100\n";
                echo "        Entity: {$matchResult['breakdown']['entity']['score']} pts ({$matchResult['breakdown']['entity']['match']})\n";
                echo "        Date: {$matchResult['breakdown']['date']['score']} pts ({$matchResult['breakdown']['date']['match']}, {$matchResult['breakdown']['date']['diff_days']} days diff)\n";
                echo "        Amount: {$matchResult['breakdown']['amount']['score']} pts ({$matchResult['breakdown']['amount']['match']}, {$matchResult['breakdown']['amount']['diff_percent']}% diff)\n";
            }
        } else {
            echo "   ❌ No Brookfield invoice found in Xero import\n";
        }
    }
}

echo "\n=== END DEBUG ===\n";

