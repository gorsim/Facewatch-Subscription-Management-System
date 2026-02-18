<?php
/**
 * Test Status Change Diagnostic
 * Tests if the status change functionality works
 */

session_start();
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/InvoiceStatusService.php';

use App\Database;
use App\Services\InvoiceStatusService;

$db = Database::getInstance();

echo "<h1>Status Change Diagnostic</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    table { border-collapse: collapse; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f0f0f0; }
</style>";

// Check 1: Does invoice_status_history table exist?
echo "<h2>1. Check if invoice_status_history table exists</h2>";
try {
    $tables = $db->fetchAll("SHOW TABLES LIKE 'invoice_status_history'");
    if (count($tables) > 0) {
        echo "<p class='success'>✅ Table 'invoice_status_history' exists</p>";
        
        // Show table structure
        $structure = $db->fetchAll("DESCRIBE invoice_status_history");
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($structure as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ Table 'invoice_status_history' does NOT exist</p>";
        echo "<p class='info'>Creating table now...</p>";
        
        // Create the table
        $db->query("
            CREATE TABLE IF NOT EXISTS invoice_status_history (
                id INT PRIMARY KEY AUTO_INCREMENT,
                invoice_id INT NOT NULL,
                old_status ENUM('draft', 'issued', 'reconciled_to_xero', 'cancelled', 'merged', 'forecast'),
                new_status ENUM('draft', 'issued', 'reconciled_to_xero', 'cancelled', 'merged', 'forecast') NOT NULL,
                changed_by VARCHAR(100) NOT NULL,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                notes TEXT,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                INDEX idx_invoice_status (invoice_id, changed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p class='success'>✅ Table created successfully!</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// Check 2: Get a draft invoice to test with
echo "<h2>2. Find a draft invoice to test</h2>";
try {
    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE invoice_status = 'draft' LIMIT 1");
    if ($invoice) {
        echo "<p class='success'>✅ Found draft invoice: {$invoice['invoice_number']} (ID: {$invoice['id']})</p>";
        echo "<p>Current status: <strong>{$invoice['invoice_status']}</strong></p>";
        
        // Check 3: Test the status change
        echo "<h2>3. Test changing status to 'issued'</h2>";
        
        // Set a user email in session if not set
        if (!isset($_SESSION['user_email'])) {
            $_SESSION['user_email'] = 'test@facewatch.co.uk';
            echo "<p class='info'>ℹ️ Set session user_email to: test@facewatch.co.uk</p>";
        }
        
        try {
            $statusService = new InvoiceStatusService();
            
            // Try to change status
            $result = $statusService->changeStatus(
                $invoice['id'],
                'issued',
                $_SESSION['user_email'],
                'Test status change from diagnostic tool'
            );
            
            if ($result) {
                echo "<p class='success'>✅ Status change successful!</p>";
                
                // Verify the change
                $updatedInvoice = $db->fetchOne("SELECT * FROM invoices WHERE id = :id", ['id' => $invoice['id']]);
                echo "<p>New status: <strong>{$updatedInvoice['invoice_status']}</strong></p>";
                
                // Check history
                $history = $db->fetchAll("SELECT * FROM invoice_status_history WHERE invoice_id = :id", ['id' => $invoice['id']]);
                echo "<p>Status history entries: " . count($history) . "</p>";
                
                if (count($history) > 0) {
                    echo "<table>";
                    echo "<tr><th>Old Status</th><th>New Status</th><th>Changed By</th><th>Changed At</th><th>Notes</th></tr>";
                    foreach ($history as $h) {
                        echo "<tr>";
                        echo "<td>{$h['old_status']}</td>";
                        echo "<td>{$h['new_status']}</td>";
                        echo "<td>{$h['changed_by']}</td>";
                        echo "<td>{$h['changed_at']}</td>";
                        echo "<td>{$h['notes']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                
                // Change it back to draft for further testing
                echo "<p class='info'>ℹ️ Changing status back to 'draft' for further testing...</p>";
                $db->query("UPDATE invoices SET invoice_status = 'draft' WHERE id = :id", ['id' => $invoice['id']]);
                echo "<p class='success'>✅ Status reset to draft</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error changing status: " . $e->getMessage() . "</p>";
            echo "<p class='error'>Stack trace: <pre>" . $e->getTraceAsString() . "</pre></p>";
        }
        
    } else {
        echo "<p class='error'>❌ No draft invoices found to test with</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='?page=invoices'>← Back to Invoices</a></p>";

