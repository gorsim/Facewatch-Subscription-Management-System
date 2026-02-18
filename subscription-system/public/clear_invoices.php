<?php
/**
 * Clear All Invoices - Emergency Cleanup Tool
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "<h1>Clear All Invoices</h1>";

// If confirmed, delete everything
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_clear'])) {
    try {
        // Step 1: Delete all camera allocations
        $allocationsDeleted = $db->query("DELETE FROM invoice_camera_allocations")->rowCount();
        
        // Step 2: Delete all invoices
        $invoicesDeleted = $db->query("DELETE FROM invoices")->rowCount();
        
        // Step 3: Reset auto-increment
        $db->query("ALTER TABLE invoices AUTO_INCREMENT = 1");
        $db->query("ALTER TABLE invoice_camera_allocations AUTO_INCREMENT = 1");
        
        echo "<div style='background-color: #d4edda; border: 2px solid #28a745; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h2 style='color: #155724; margin: 0;'>✅ Success!</h2>";
        echo "<p style='margin: 10px 0 0 0; font-size: 16px;'>";
        echo "Deleted <strong>$invoicesDeleted invoices</strong> and <strong>$allocationsDeleted camera allocations</strong>.";
        echo "</p>";
        echo "<p style='margin: 10px 0 0 0;'>";
        echo "<a href='?page=invoices' style='color: #007bff; text-decoration: none; font-weight: bold;'>← Go to Invoices Page</a>";
        echo "</p>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='background-color: #f8d7da; border: 2px solid #dc3545; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h2 style='color: #721c24; margin: 0;'>❌ Error!</h2>";
        echo "<p style='margin: 10px 0 0 0;'>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
} else {
    // Show confirmation form
    
    // Count current invoices
    $invoiceCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoices")['count'] ?? 0;
    $allocationCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoice_camera_allocations")['count'] ?? 0;
    
    echo "<div style='background-color: #fff3cd; border: 3px solid #ffc107; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h2 style='color: #856404; margin: 0;'>⚠️ Warning!</h2>";
    echo "<p style='margin: 10px 0; font-size: 16px;'>";
    echo "This will <strong>permanently delete ALL invoices</strong> from the system.";
    echo "</p>";
    echo "<p style='margin: 10px 0; font-size: 16px;'>";
    echo "Current database contains:";
    echo "</p>";
    echo "<ul style='font-size: 16px;'>";
    echo "<li><strong>$invoiceCount invoices</strong></li>";
    echo "<li><strong>$allocationCount camera allocations</strong></li>";
    echo "</ul>";
    echo "<p style='margin: 10px 0; font-weight: bold; color: #721c24;'>";
    echo "⚠️ This action CANNOT be undone!";
    echo "</p>";
    echo "</div>";
    
    // Show all invoices that will be deleted
    echo "<h2>Invoices to be Deleted:</h2>";
    $invoices = $db->fetchAll(
        "SELECT i.invoice_number, i.invoice_status, le.legal_entity_name, i.invoice_amount
         FROM invoices i
         JOIN legal_entities le ON i.legal_entity_id = le.id
         ORDER BY i.id"
    );
    
    if (!empty($invoices)) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
        echo "<tr style='background-color: #f8f9fa;'>";
        echo "<th>Invoice #</th><th>Legal Entity</th><th>Status</th><th>Amount</th>";
        echo "</tr>";
        foreach ($invoices as $inv) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($inv['invoice_number']) . "</td>";
            echo "<td>" . htmlspecialchars($inv['legal_entity_name']) . "</td>";
            echo "<td>" . htmlspecialchars($inv['invoice_status']) . "</td>";
            echo "<td>£" . number_format($inv['invoice_amount'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<form method='POST' style='margin: 20px 0;'>";
    echo "<input type='hidden' name='confirm_clear' value='1'>";
    echo "<button type='submit' style='background-color: #dc3545; color: white; padding: 15px 30px; font-size: 18px; font-weight: bold; border: none; border-radius: 5px; cursor: pointer;'>";
    echo "🗑️ Yes, Delete ALL Invoices";
    echo "</button>";
    echo " ";
    echo "<a href='?page=invoices' style='display: inline-block; background-color: #6c757d; color: white; padding: 15px 30px; font-size: 18px; font-weight: bold; text-decoration: none; border-radius: 5px;'>";
    echo "← Cancel and Go Back";
    echo "</a>";
    echo "</form>";
}
?>

