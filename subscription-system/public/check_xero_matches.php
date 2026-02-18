<?php
/**
 * Diagnostic Tool: Check Xero CSV Matches
 * Upload your CSV to see which Contact Names will match Legal Entities
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

// Get all legal entities with Xero Company Names
$entities = $db->fetchAll("
    SELECT
        id,
        legal_entity_name,
        xero_company_name
    FROM legal_entities
    WHERE xero_company_name IS NOT NULL AND xero_company_name != ''
    ORDER BY xero_company_name
");

// Create lookup map (case-insensitive)
$dbMap = [];
foreach ($entities as $entity) {
    $dbMap[strtolower($entity['xero_company_name'])] = $entity;
}

// Handle CSV upload
$csvNames = [];
$uploadError = '';
$debugInfo = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debugInfo[] = 'POST request received';

    if (isset($_FILES['csv_file'])) {
        $debugInfo[] = 'File upload detected';
        $file = $_FILES['csv_file'];
        $debugInfo[] = 'File error code: ' . $file['error'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $debugInfo[] = 'File uploaded successfully, opening file...';
        $handle = fopen($file['tmp_name'], 'r');

        if ($handle) {
            $debugInfo[] = 'File opened successfully';
            // Read header
            $header = fgetcsv($handle);
            if ($header) {
                $debugInfo[] = 'Header row read: ' . implode(', ', $header);
                // Find Contact Name column (case-insensitive)
                $headerLower = array_map('strtolower', array_map('trim', $header));
                $contactNameIndex = false;

                foreach ($headerLower as $index => $col) {
                    if (in_array($col, ['contact name', 'contact_name', 'xero company name', 'xero_company_name'])) {
                        $contactNameIndex = $index;
                        $debugInfo[] = 'Found Contact Name column at index ' . $index . ' (column: ' . $header[$index] . ')';
                        break;
                    }
                }

                if ($contactNameIndex !== false) {
                    // Read all rows and extract contact names
                    $rowCount = 0;
                    while (($row = fgetcsv($handle)) !== false) {
                        $rowCount++;
                        if (isset($row[$contactNameIndex]) && !empty(trim($row[$contactNameIndex]))) {
                            $csvNames[] = trim($row[$contactNameIndex]);
                        }
                    }

                    $csvNames = array_unique($csvNames);
                    sort($csvNames);
                    $debugInfo[] = 'Processed ' . $rowCount . ' data rows, found ' . count($csvNames) . ' unique Contact Names';
                } else {
                    $uploadError = 'Could not find "Contact Name" or "Xero Company Name" column in CSV. Found columns: ' . implode(', ', $header);
                }
            } else {
                $uploadError = 'Could not read header row from CSV';
            }

            fclose($handle);
        } else {
            $uploadError = 'Could not open uploaded file';
        }
    } else {
        $uploadError = 'File upload failed with error code: ' . $file['error'];
    }
    } else {
        $uploadError = 'No file was uploaded';
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Xero CSV Match Checker</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .match { background: #d4edda; padding: 8px; margin: 3px 0; border-radius: 4px; border-left: 4px solid #28a745; }
        .no-match { background: #f8d7da; padding: 8px; margin: 3px 0; border-radius: 4px; border-left: 4px solid #dc3545; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background: #2980b9; }
        .upload-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
        .summary-box { background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3; }
        .summary-box h3 { margin-bottom: 10px; }
        .summary-stat { font-size: 1.2em; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Xero CSV Match Checker</h1>
            <p style="margin-top: 10px; opacity: 0.9;">Upload your Xero CSV to see which Contact Names will match your Legal Entities</p>
        </div>

        <?php if ($uploadError): ?>
            <div class="alert-error">
                <strong>❌ Error:</strong> <?= htmlspecialchars($uploadError) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>📤 Upload Your Xero CSV</h2>

            <?php if (!empty($uploadError)): ?>
                <div class="alert-error">
                    <strong>⚠️ Upload Error:</strong> <?= htmlspecialchars($uploadError) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($debugInfo)): ?>
                <div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 12px;">
                    <strong>Debug Info:</strong><br>
                    <?php foreach ($debugInfo as $info): ?>
                        • <?= htmlspecialchars($info) ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="upload-box">
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="csv_file" accept=".csv" required style="margin-bottom: 10px;">
                    <button type="submit" class="btn">🔍 Check Matches</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>📋 Your Database - Legal Entities with Xero Company Names (<?= count($entities) ?>)</h2>
            <p style="color: #666; margin-bottom: 15px;">
                These are the Xero Company Names in your database. Your CSV's "Contact Name" must match one of these (case-insensitive).
            </p>

            <?php if (empty($entities)): ?>
                <div class="alert-error">
                    <strong>⚠️ No Legal Entities Found!</strong>
                    <p>You don't have any Legal Entities with Xero Company Names set.</p>
                </div>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Legal Entity Name</th>
                        <th>Xero Company Name</th>
                        <th>ID</th>
                    </tr>
                    <?php foreach ($entities as $entity): ?>
                        <tr>
                            <td><?= htmlspecialchars($entity['legal_entity_name']) ?></td>
                            <td><strong><?= htmlspecialchars($entity['xero_company_name']) ?></strong></td>
                            <td><?= htmlspecialchars($entity['id']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <?php if (!empty($csvNames)): ?>
            <div class="card">
                <h2>🎯 Match Results (<?= count($csvNames) ?> unique Contact Names in CSV)</h2>

                <?php
                $matched = 0;
                $unmatched = 0;
                $matchedNames = [];
                $unmatchedNames = [];

                foreach ($csvNames as $csvName) {
                    $key = strtolower($csvName);
                    $isMatch = isset($dbMap[$key]);

                    if ($isMatch) {
                        $matched++;
                        $matchedNames[] = [
                            'csv' => $csvName,
                            'entity' => $dbMap[$key]
                        ];
                    } else {
                        $unmatched++;
                        $unmatchedNames[] = $csvName;
                    }
                }
                ?>

                <div class="summary-box" style="margin-bottom: 20px;">
                    <h3>Summary</h3>
                    <div class="summary-stat">✅ <strong>Matched:</strong> <?= $matched ?> / <?= count($csvNames) ?> (<?= round(($matched / count($csvNames)) * 100, 1) ?>%)</div>
                    <div class="summary-stat">❌ <strong>Unmatched:</strong> <?= $unmatched ?> / <?= count($csvNames) ?></div>
                </div>

                <?php if (!empty($matchedNames)): ?>
                    <h3 style="margin-top: 20px; color: #28a745;">✅ Matched Names (<?= count($matchedNames) ?>)</h3>
                    <p style="color: #666; margin-bottom: 10px;">These invoices will import successfully:</p>
                    <?php foreach ($matchedNames as $match): ?>
                        <div class="match">
                            ✅ <strong><?= htmlspecialchars($match['csv']) ?></strong>
                            → Matches: <?= htmlspecialchars($match['entity']['legal_entity_name']) ?> (<?= htmlspecialchars($match['entity']['id']) ?>)
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($unmatchedNames)): ?>
                    <h3 style="margin-top: 20px; color: #dc3545;">❌ Unmatched Names (<?= count($unmatchedNames) ?>)</h3>
                    <p style="color: #666; margin-bottom: 10px;">These invoices will be SKIPPED during import:</p>
                    <?php foreach ($unmatchedNames as $name): ?>
                        <div class="no-match">
                            ❌ <strong><?= htmlspecialchars($name) ?></strong>
                            → <span style="color: #721c24;">NO MATCH FOUND - You need to add this Xero Company Name to a Legal Entity first</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php?page=import&type=xero" class="btn">⬅️ Back to Xero Import</a>
        </div>
    </div>
</body>
</html>

