<?php
/**
 * Check Table Structure
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "<h1>Table Structure Check</h1>";

// Check invoice_camera_allocations table
echo "<h2>invoice_camera_allocations table</h2>";
$columns = $db->fetchAll("DESCRIBE invoice_camera_allocations");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>" . $col['Field'] . "</td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . $col['Null'] . "</td>";
    echo "<td>" . $col['Key'] . "</td>";
    echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
    echo "<td>" . $col['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Show sample data
echo "<h2>Sample Data</h2>";
$sample = $db->fetchAll("SELECT * FROM invoice_camera_allocations LIMIT 10");
if (empty($sample)) {
    echo "<p>No data in table</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>";
    foreach (array_keys($sample[0]) as $key) {
        echo "<th>$key</th>";
    }
    echo "</tr>";
    foreach ($sample as $row) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><a href='check_cameras.php'>← Back to Camera Check</a></p>";

