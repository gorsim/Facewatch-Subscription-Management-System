<?php
/**
 * Run Migration 021: Update Pricing Structure
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

echo "<h1>Running Migration 021: Update Pricing Structure</h1>\n";

try {
    $db = Database::getInstance();
    
    // Read the migration file
    $migrationFile = __DIR__ . '/../database/migrations/021_update_pricing_structure.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Remove comments and split into statements
    $lines = explode("\n", $sql);
    $currentStatement = '';
    $statements = [];
    $inString = false;
    $stringChar = '';
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Skip empty lines and comment-only lines
        if (empty($trimmed) || preg_match('/^--/', $trimmed)) {
            continue;
        }
        
        // Skip USE statements
        if (preg_match('/^USE\s+/i', $trimmed)) {
            continue;
        }
        
        $currentStatement .= $line . "\n";
        
        // Check if statement is complete (ends with semicolon outside of strings)
        for ($i = 0; $i < strlen($line); $i++) {
            $char = $line[$i];
            
            if (($char === "'" || $char === '"') && !$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar && $inString) {
                $inString = false;
            }
        }
        
        if (!$inString && substr(rtrim($line), -1) === ';') {
            $stmt = trim($currentStatement);
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
            $currentStatement = '';
        }
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
            } elseif (stripos($statement, 'DROP TABLE') !== false) {
                preg_match('/DROP TABLE.*?`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<p>✓ Dropped table: <strong>$tableName</strong></p>\n";
            } elseif (stripos($statement, 'INSERT INTO') !== false) {
                preg_match('/INSERT INTO\s+`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<p>✓ Inserted data into: <strong>$tableName</strong></p>\n";
            } elseif (stripos($statement, 'ALTER TABLE') !== false) {
                preg_match('/ALTER TABLE\s+`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<p>✓ Altered table: <strong>$tableName</strong></p>\n";
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
            echo "<li><strong>Statement:</strong> " . htmlspecialchars($error['statement']) . "<br>";
            echo "<strong>Error:</strong> " . htmlspecialchars($error['error']) . "</li>\n";
        }
        echo "</ul>\n";
    }
    
    // Verify tables exist
    echo "<hr>\n";
    echo "<h2>Verification</h2>\n";
    
    $tables = ['camera_pricing'];
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
    echo "<h2>Camera Pricing Tiers</h2>\n";
    $pricing = $db->fetchAll("SELECT * FROM camera_pricing ORDER BY effective_date DESC, min_cameras ASC");
    if (!empty($pricing)) {
        echo "<table border='1' cellpadding='5'>\n";
        echo "<tr><th>Effective Date</th><th>Camera Range</th><th>P/A</th><th>P/Q</th><th>P/M</th><th>Notes</th></tr>\n";
        foreach ($pricing as $p) {
            $range = $p['min_cameras'] . '-' . ($p['max_cameras'] ?? '∞');
            echo "<tr>";
            echo "<td>{$p['effective_date']}</td>";
            echo "<td>{$range}</td>";
            echo "<td>£{$p['price_per_annum']}</td>";
            echo "<td>£{$p['price_per_quarter']}</td>";
            echo "<td>£{$p['price_per_month']}</td>";
            echo "<td>{$p['notes']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    echo "<hr>\n";
    echo "<h2 style='color: green;'>✓ Migration 021 completed successfully!</h2>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Migration failed!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

