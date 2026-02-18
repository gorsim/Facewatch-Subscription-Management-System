<?php
/**
 * Test Camera Import
 * Direct test of the camera import functionality
 */

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Models/LegalEntity.php';
require_once __DIR__ . '/app/Services/CameraImporter.php';

use App\Services\CameraImporter;

echo "<h1>Camera Import Test</h1>";

// Check if sample file exists
$sampleFile = __DIR__ . '/sample-data/camera-counts-snapshot-template.csv';

if (!file_exists($sampleFile)) {
    echo "<p style='color: red;'>Sample file not found: {$sampleFile}</p>";
    exit;
}

echo "<p>Sample file found: {$sampleFile}</p>";

// Test the import
$importer = new CameraImporter();
$snapshotDate = date('Y-m-d'); // Today

echo "<p>Testing import with snapshot date: {$snapshotDate}</p>";

$success = $importer->import($sampleFile, $snapshotDate);

if ($success) {
    echo "<p style='color: green; font-weight: bold;'>✅ Import successful!</p>";
    echo "<p>Imported: {$importer->getImported()} records</p>";
    echo "<p>Updated: {$importer->getSkipped()} records</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Import failed!</p>";
    $errors = $importer->getErrors();
    if (!empty($errors)) {
        echo "<h3>Errors:</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    }
}

// Check the PHP error log
echo "<h2>Recent Error Log</h2>";
echo "<pre>";
$errorLog = file_get_contents('/Applications/MAMP/logs/php_error.log');
$lines = explode("\n", $errorLog);
$recentLines = array_slice($lines, -30);
foreach ($recentLines as $line) {
    if (strpos($line, 'CameraImporter') !== false || strpos($line, 'Import:') !== false) {
        echo htmlspecialchars($line) . "\n";
    }
}
echo "</pre>";

