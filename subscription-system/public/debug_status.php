<?php
/**
 * Debug Status Change - Interactive Diagnostic
 * Shows session info and tests the status change process
 */

session_start();
require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "<h1>🔍 Status Change Debug Tool</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
    .success { background: #d4edda; border: 2px solid #28a745; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { background: #f8d7da; border: 2px solid #dc3545; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .info { background: #d1ecf1; border: 2px solid #17a2b8; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .warning { background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 10px 0; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #f0f0f0; font-weight: bold; }
    .btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 16px; }
    .btn-success { background: #28a745; }
    .btn-danger { background: #dc3545; }
    .code { background: #f5f5f5; padding: 10px; border-left: 3px solid #007bff; margin: 10px 0; font-family: monospace; }
</style>";

// Section 1: Session Information
echo "<h2>1️⃣ Session Information</h2>";
echo "<div class='info'>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? "✅ Active" : "❌ Not Active") . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>User Logged In:</strong> " . (isset($_SESSION['user_id']) ? "✅ Yes (User ID: {$_SESSION['user_id']})" : "❌ No") . "</p>";
echo "<p><strong>Username:</strong> " . ($_SESSION['user'] ?? 'Not set') . "</p>";

if (isset($_SESSION['success'])) {
    echo "<p class='success'>✅ Success Message: " . htmlspecialchars($_SESSION['success']) . "</p>";
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo "<p class='error'>❌ Error Message: " . htmlspecialchars($_SESSION['error']) . "</p>";
    unset($_SESSION['error']);
}
echo "</div>";

// Section 2: Find a Draft Invoice
echo "<h2>2️⃣ Available Draft Invoices</h2>";
$draftInvoices = $db->fetchAll("
    SELECT i.id, i.invoice_number, i.invoice_status, le.legal_entity_name, i.invoice_amount
    FROM invoices i
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE i.invoice_status = 'draft'
    ORDER BY i.id DESC
    LIMIT 5
");

if (empty($draftInvoices)) {
    echo "<div class='warning'><p>⚠️ No draft invoices found. Create an invoice first!</p></div>";
} else {
    echo "<table>";
    echo "<tr><th>Invoice #</th><th>Legal Entity</th><th>Amount</th><th>Status</th><th>Actions</th></tr>";
    foreach ($draftInvoices as $inv) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($inv['invoice_number']) . "</td>";
        echo "<td>" . htmlspecialchars($inv['legal_entity_name']) . "</td>";
        echo "<td>£" . number_format($inv['invoice_amount'], 2) . "</td>";
        echo "<td>" . htmlspecialchars($inv['invoice_status']) . "</td>";
        echo "<td>";
        echo "<a href='index.php?page=invoices&action=change_status&id={$inv['id']}&status=issued' class='btn btn-success'>📤 Mark as Issued</a>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Section 3: Test the URL
if (!empty($draftInvoices)) {
    $testInvoice = $draftInvoices[0];
    echo "<h2>3️⃣ Test URL</h2>";
    echo "<div class='info'>";
    echo "<p>The 'Mark as Issued' button should redirect to this URL:</p>";
    echo "<div class='code'>";
    echo "?page=invoices&action=change_status&id={$testInvoice['id']}&status=issued";
    echo "</div>";
    echo "<p><strong>Click this link to test:</strong></p>";
    echo "<a href='index.php?page=invoices&action=change_status&id={$testInvoice['id']}&status=issued' class='btn btn-success'>🧪 Test Status Change</a>";
    echo "</div>";
}

// Section 4: Check if change_status.php exists
echo "<h2>4️⃣ File Check</h2>";
$changeStatusFile = __DIR__ . '/../views/invoices/change_status.php';
if (file_exists($changeStatusFile)) {
    echo "<div class='success'><p>✅ File exists: views/invoices/change_status.php</p></div>";
} else {
    echo "<div class='error'><p>❌ File NOT found: views/invoices/change_status.php</p></div>";
}

// Section 5: Recent Status Changes
echo "<h2>5️⃣ Recent Status Changes</h2>";
$recentChanges = $db->fetchAll("
    SELECT h.*, i.invoice_number
    FROM invoice_status_history h
    JOIN invoices i ON h.invoice_id = i.id
    ORDER BY h.changed_at DESC
    LIMIT 10
");

if (empty($recentChanges)) {
    echo "<div class='info'><p>ℹ️ No status changes recorded yet</p></div>";
} else {
    echo "<table>";
    echo "<tr><th>Invoice</th><th>Old Status</th><th>New Status</th><th>Changed By</th><th>When</th><th>Notes</th></tr>";
    foreach ($recentChanges as $change) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($change['invoice_number']) . "</td>";
        echo "<td>" . htmlspecialchars($change['old_status']) . "</td>";
        echo "<td>" . htmlspecialchars($change['new_status']) . "</td>";
        echo "<td>" . htmlspecialchars($change['changed_by']) . "</td>";
        echo "<td>" . htmlspecialchars($change['changed_at']) . "</td>";
        echo "<td>" . htmlspecialchars($change['notes'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><a href='index.php?page=invoices' class='btn'>← Back to Invoices</a></p>";

