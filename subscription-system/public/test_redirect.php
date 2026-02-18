<?php
/**
 * Simple Redirect Test
 * Tests if basic redirects work
 */

session_start();

echo "<h1>🧪 Redirect Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .btn { display: inline-block; padding: 15px 30px; margin: 10px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-size: 18px; }
    .info { background: #d1ecf1; border: 2px solid #17a2b8; padding: 20px; margin: 20px 0; border-radius: 5px; }
</style>";

// Test 1: Check current URL
echo "<h2>Test 1: Current URL</h2>";
echo "<div class='info'>";
echo "<p><strong>Current URL:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>Query String:</strong> " . ($_SERVER['QUERY_STRING'] ?? 'none') . "</p>";
echo "<p><strong>Page Parameter:</strong> " . ($_GET['page'] ?? 'not set') . "</p>";
echo "<p><strong>Action Parameter:</strong> " . ($_GET['action'] ?? 'not set') . "</p>";
echo "</div>";

// Test 2: Try a simple redirect
if (isset($_GET['test']) && $_GET['test'] === 'redirect') {
    $_SESSION['test_message'] = "✅ Redirect worked! You were redirected from test_redirect.php";
    header('Location: debug_status.php');
    exit;
}

// Test 3: Check if we came from a redirect
if (isset($_SESSION['test_message'])) {
    echo "<div style='background: #d4edda; border: 2px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h2>" . $_SESSION['test_message'] . "</h2>";
    echo "</div>";
    unset($_SESSION['test_message']);
}

echo "<h2>Test 2: Click to Test Redirect</h2>";
echo "<p>This will test if basic PHP redirects work on your server:</p>";
echo "<a href='test_redirect.php?test=redirect' class='btn'>🔄 Test Redirect</a>";

echo "<hr>";

echo "<h2>Test 3: Test Invoice Status Change URL</h2>";
echo "<p>This will try to access the change_status page directly:</p>";
echo "<a href='?page=invoices&action=change_status&id=1&status=issued' class='btn'>📤 Test Change Status URL</a>";

echo "<hr>";

echo "<h2>Test 4: Check Routing</h2>";
echo "<div class='info'>";
echo "<p>Let's check if the routing is working correctly...</p>";

// Simulate what index.php does
$page = $_GET['page'] ?? 'none';
$action = $_GET['action'] ?? 'none';

echo "<p><strong>Page:</strong> $page</p>";
echo "<p><strong>Action:</strong> $action</p>";

if ($page === 'invoices' && $action === 'change_status') {
    $changeStatusFile = __DIR__ . '/../views/invoices/change_status.php';
    if (file_exists($changeStatusFile)) {
        echo "<p style='color: green; font-weight: bold;'>✅ The change_status.php file exists and SHOULD be loaded by index.php</p>";
        echo "<p><strong>File path:</strong> $changeStatusFile</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ The change_status.php file does NOT exist!</p>";
    }
} else {
    echo "<p>ℹ️ Not on the change_status page (page=$page, action=$action)</p>";
}
echo "</div>";

echo "<hr>";
echo "<p><a href='debug_status.php'>← Back to Debug Status</a></p>";

