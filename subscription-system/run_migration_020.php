<?php
/**
 * Run Migration 020: Create Rate History System
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

echo "<h1>Running Migration 020: Create Rate History System</h1>\n";

try {
    $db = Database::getInstance();

    // Read the migration file
    $migrationFile = __DIR__ . '/../database/migrations/020_create_rate_history_system.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }

    $sql = file_get_contents($migrationFile);

    // Remove comments and split into statements
    $lines = explode("\n", $sql);
    $cleanedLines = [];

    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and comment lines
        if (empty($line) || substr($line, 0, 2) === '--') {
            continue;
        }
        // Skip USE statements
        if (stripos($line, 'USE ') === 0) {
            continue;
        }
        $cleanedLines[] = $line;
    }

    $cleanedSql = implode("\n", $cleanedLines);

    // Split on semicolons but be careful with semicolons inside strings
    $statements = [];
    $currentStatement = '';
    $inString = false;
    $stringChar = '';

    for ($i = 0; $i < strlen($cleanedSql); $i++) {
        $char = $cleanedSql[$i];

        if (($char === '"' || $char === "'") && ($i === 0 || $cleanedSql[$i-1] !== '\\')) {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
            }
        }

        if ($char === ';' && !$inString) {
            $stmt = trim($currentStatement);
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
            $currentStatement = '';
        } else {
            $currentStatement .= $char;
        }
    }

    // Add last statement if exists
    $stmt = trim($currentStatement);
    if (!empty($stmt)) {
        $statements[] = $stmt;
    }

    echo "<p>Found " . count($statements) . " SQL statements to execute.</p>\n";

    $executed = 0;
    $errors = [];

    foreach ($statements as $index => $statement) {
        try {
            $db->query($statement);
            $executed++;

            // Show progress for major operations
            if (stripos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<p>✓ Created table: <strong>$tableName</strong></p>\n";
            } elseif (stripos($statement, 'INSERT INTO') !== false) {
                preg_match('/INSERT INTO\s+`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<p>✓ Inserted data into: <strong>$tableName</strong></p>\n";
            }

        } catch (Exception $e) {
            $errors[] = [
                'statement' => substr($statement, 0, 100) . '...',
                'error' => $e->getMessage()
            ];
            echo "<p style='color: orange;'>⚠ Warning on statement " . ($index + 1) . ": " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
    echo "<hr>\n";
    echo "<h2>Migration Summary</h2>\n";
    echo "<p><strong>Executed:</strong> $executed statements</p>\n";
    echo "<p><strong>Errors:</strong> " . count($errors) . "</p>\n";
    
    if (!empty($errors)) {
        echo "<h3>Errors:</h3>\n";
        echo "<ul>\n";
        foreach ($errors as $error) {
            echo "<li>\n";
            echo "<strong>Statement:</strong> " . htmlspecialchars($error['statement']) . "<br>\n";
            echo "<strong>Error:</strong> " . htmlspecialchars($error['error']) . "\n";
            echo "</li>\n";
        }
        echo "</ul>\n";
    }
    
    // Verify tables were created
    echo "<hr>\n";
    echo "<h2>Verification</h2>\n";
    
    $tables = [
        'legal_entity_rate_history',
        'volume_discount_tiers',
        'base_camera_rates',
        'inflation_adjustments'
    ];
    
    foreach ($tables as $table) {
        $result = $db->fetchOne("SHOW TABLES LIKE '$table'");
        if ($result) {
            $count = $db->fetchOne("SELECT COUNT(*) as count FROM $table");
            echo "<p>✓ Table <strong>$table</strong> exists with {$count['count']} rows</p>\n";
        } else {
            echo "<p>✗ Table <strong>$table</strong> NOT FOUND</p>\n";
        }
    }
    
    // Show sample data
    echo "<hr>\n";
    echo "<h2>Sample Data</h2>\n";
    
    echo "<h3>Volume Discount Tiers</h3>\n";
    $tiers = $db->fetchAll("SELECT * FROM volume_discount_tiers ORDER BY min_cameras");
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Tier</th><th>Min Cameras</th><th>Max Cameras</th><th>Discount %</th></tr>\n";
    foreach ($tiers as $tier) {
        echo "<tr>";
        echo "<td>{$tier['tier_name']}</td>";
        echo "<td>{$tier['min_cameras']}</td>";
        echo "<td>" . ($tier['max_cameras'] ?? 'Unlimited') . "</td>";
        echo "<td>{$tier['discount_percentage']}%</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>Base Camera Rates</h3>\n";
    $baseRates = $db->fetchAll("SELECT * FROM base_camera_rates ORDER BY effective_date DESC");
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Effective Date</th><th>Main Rate</th><th>Additional Rate</th><th>Notes</th></tr>\n";
    foreach ($baseRates as $rate) {
        echo "<tr>";
        echo "<td>{$rate['effective_date']}</td>";
        echo "<td>£{$rate['main_camera_rate']}</td>";
        echo "<td>£{$rate['additional_camera_rate']}</td>";
        echo "<td>{$rate['notes']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>Rate History (Sample - First 10)</h3>\n";
    $history = $db->fetchAll("SELECT rh.*, le.legal_entity_name 
                               FROM legal_entity_rate_history rh
                               JOIN legal_entities le ON rh.legal_entity_id = le.id
                               ORDER BY rh.created_at DESC
                               LIMIT 10");
    if (!empty($history)) {
        echo "<table border='1' cellpadding='5'>\n";
        echo "<tr><th>Entity</th><th>Effective Date</th><th>Main Rate</th><th>Additional Rate</th><th>Cameras</th><th>Discount %</th><th>Reason</th></tr>\n";
        foreach ($history as $h) {
            echo "<tr>";
            echo "<td>{$h['legal_entity_name']}</td>";
            echo "<td>{$h['effective_date']}</td>";
            echo "<td>£{$h['main_camera_rate']}</td>";
            echo "<td>£{$h['additional_camera_rate']}</td>";
            echo "<td>{$h['total_cameras']}</td>";
            echo "<td>{$h['discount_percentage']}%</td>";
            echo "<td>{$h['change_reason']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>No rate history found.</p>\n";
    }
    
    echo "<hr>\n";
    echo "<h2 style='color: green;'>✓ Migration 020 completed successfully!</h2>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Migration failed!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

