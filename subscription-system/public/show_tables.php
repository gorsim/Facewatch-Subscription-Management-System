<?php
/**
 * Show all tables in the database
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

echo "<h1>Database Tables</h1>";
echo "<pre>";

$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

echo "Tables in facewatch_subscriptions database:\n\n";
foreach ($tables as $table) {
    echo "  - $table\n";
}

echo "</pre>";

