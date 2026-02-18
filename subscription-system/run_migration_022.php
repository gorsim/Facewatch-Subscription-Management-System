<?php
/**
 * Run Migration 022: Add Entity-Specific Pricing
 */

require_once __DIR__ . '/app/Database.php';

use App\Database;

echo "<h1>Running Migration 022: Add Entity-Specific Pricing</h1>\n";

try {
    $db = Database::getInstance();
    
    // Read the migration file
    $migrationFile = __DIR__ . '/../database/migrations/022_add_entity_specific_pricing.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Remove comments and split into statements
    $lines = explode("\n", $sql);
    $currentStatement = '';
    $statements = [];
    
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
        
        // Check if statement is complete
        if (substr(rtrim($line), -1) === ';') {
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
            if (stripos($statement, 'ALTER TABLE') !== false) {
                preg_match('/ALTER TABLE\s+`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<p>✓ Altered table: <strong>$tableName</strong></p>\n";
            } elseif (stripos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<p>✓ Created table: <strong>$tableName</strong></p>\n";
            } elseif (stripos($statement, 'INSERT INTO') !== false) {
                preg_match('/INSERT INTO\s+`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<p>✓ Inserted data into: <strong>$tableName</strong></p>\n";
            } elseif (stripos($statement, 'UPDATE') !== false) {
                preg_match('/UPDATE\s+`?(\w+)`?/i', $statement, $matches);
                $tableName = $matches[1] ?? 'unknown';
                echo "<p>✓ Updated table: <strong>$tableName</strong></p>\n";
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
            echo "<li><strong>Statement:</strong> " . htmlspecialchars($error['statement']) . "<br><strong>Error:</strong> " . htmlspecialchars($error['error']) . "</li>\n";
        }
        echo "</ul>\n";
    }
    
    // Verify tables exist
    echo "<hr>\n";
    echo "<h2>Verification</h2>\n";
    
    $result = $db->fetchOne("SHOW TABLES LIKE 'legal_entity_pricing'");
    if ($result) {
        $count = $db->fetchOne("SELECT COUNT(*) as count FROM legal_entity_pricing");
        echo "<p>✓ Table <strong>legal_entity_pricing</strong> exists with {$count['count']} rows</p>\n";
    } else {
        echo "<p>✗ Table <strong>legal_entity_pricing</strong> NOT FOUND</p>\n";
    }
    
    // Show entities with custom pricing
    echo "<hr>\n";
    echo "<h2>Legal Entities by Pricing Type</h2>\n";
    $entities = $db->fetchAll("
        SELECT 
            le.legal_entity_name,
            le.pricing_type,
            COUNT(lep.id) as custom_tiers
        FROM legal_entities le
        LEFT JOIN legal_entity_pricing lep ON le.id = lep.legal_entity_id
        GROUP BY le.id, le.legal_entity_name, le.pricing_type
        ORDER BY le.pricing_type, le.legal_entity_name
    ");
    
    if (!empty($entities)) {
        echo "<table border='1' cellpadding='5'>\n";
        echo "<tr><th>Legal Entity</th><th>Pricing Type</th><th>Custom Tiers</th></tr>\n";
        foreach ($entities as $e) {
            $badge = $e['pricing_type'] === 'custom' ? '🎯' : '📊';
            echo "<tr>";
            echo "<td>{$badge} {$e['legal_entity_name']}</td>";
            echo "<td><strong>{$e['pricing_type']}</strong></td>";
            echo "<td>{$e['custom_tiers']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    echo "<hr>\n";
    echo "<h2 style='color: green;'>✓ Migration 022 completed successfully!</h2>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Migration failed!</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

