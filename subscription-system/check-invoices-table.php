<?php
// Direct MySQL connection
$mysqli = new mysqli('localhost', 'root', 'root', 'facewatch_subscriptions');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "🔍 Checking Invoices Table Structure\n";
echo "====================================\n\n";

$result = $mysqli->query("DESCRIBE invoices");

echo "Columns in invoices table:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - {$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Key']} {$row['Default']}\n";
}

$mysqli->close();
?>

