<?php
require_once __DIR__ . '/app/Database.php';

use App\Database;

$db = Database::getInstance();
$conn = $db->getConnection();

echo "=== Checking invoices table structure ===\n\n";

$stmt = $conn->query("DESCRIBE invoices");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    echo "{$column['Field']} - {$column['Type']}\n";
}

