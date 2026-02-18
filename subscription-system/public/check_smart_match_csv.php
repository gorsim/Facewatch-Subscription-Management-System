<?php
/**
 * Diagnostic Tool: Check Smart Match CSV Format
 * Shows what columns are in your CSV and what the system expects
 * Standalone version - no dependencies needed
 */

$debugInfo = [];
$csvData = null;
$expectedColumns = [
    'Invoice ID (or InvoiceID)',
    'Invoice Number (or InvoiceNumber)',
    'Contact Name (or ContactName)',
    'Date (or InvoiceDate)',
    'Amount Due (or Total)',
    'Status (optional)'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $debugInfo[] = 'POST request received';
    
    if (isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        $debugInfo[] = 'File upload detected: ' . $file['name'];
        $debugInfo[] = 'File error code: ' . $file['error'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $debugInfo[] = 'File uploaded successfully';
            
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle) {
                $debugInfo[] = 'File opened for reading';
                
                // Read header
                $header = fgetcsv($handle);
                $debugInfo[] = 'Header row read: ' . count($header) . ' columns';
                
                // Read first 5 data rows
                $rows = [];
                $rowCount = 0;
                while (($row = fgetcsv($handle)) !== false && $rowCount < 5) {
                    $rows[] = $row;
                    $rowCount++;
                }
                
                $debugInfo[] = 'Read ' . $rowCount . ' sample rows';
                
                fclose($handle);
                
                $csvData = [
                    'header' => $header,
                    'rows' => $rows,
                    'totalColumns' => count($header)
                ];
                
                // Check which expected columns are present
                $headerLower = array_map('strtolower', $header);
                $foundColumns = [];
                
                // Check for Invoice ID
                if (in_array('invoice id', $headerLower) || in_array('invoiceid', $headerLower)) {
                    $foundColumns[] = '✅ Invoice ID found';
                } else {
                    $foundColumns[] = '❌ Invoice ID NOT found';
                }
                
                // Check for Invoice Number
                if (in_array('invoice number', $headerLower) || in_array('invoicenumber', $headerLower)) {
                    $foundColumns[] = '✅ Invoice Number found';
                } else {
                    $foundColumns[] = '❌ Invoice Number NOT found';
                }
                
                // Check for Contact Name
                if (in_array('contact name', $headerLower) || in_array('contactname', $headerLower)) {
                    $foundColumns[] = '✅ Contact Name found';
                } else {
                    $foundColumns[] = '❌ Contact Name NOT found';
                }
                
                // Check for Date
                if (in_array('date', $headerLower) || in_array('invoicedate', $headerLower)) {
                    $foundColumns[] = '✅ Date found';
                } else {
                    $foundColumns[] = '❌ Date NOT found';
                }
                
                // Check for Amount
                if (in_array('amount due', $headerLower) || in_array('total', $headerLower)) {
                    $foundColumns[] = '✅ Amount Due/Total found';
                } else {
                    $foundColumns[] = '❌ Amount Due/Total NOT found';
                }
                
                $csvData['columnCheck'] = $foundColumns;
                
            } else {
                $debugInfo[] = 'ERROR: Could not open file';
            }
        } else {
            $debugInfo[] = 'ERROR: File upload failed with code ' . $file['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Smart Match CSV Checker</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        .debug { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
        .expected { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
        .results { background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #4CAF50; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn { background: #4CAF50; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #45a049; }
        input[type="file"] { padding: 10px; border: 2px solid #ddd; border-radius: 4px; width: 100%; margin: 10px 0; }
        .check-item { padding: 8px; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Smart Match CSV Format Checker</h1>
        
        <div class="expected">
            <h3>📋 Expected CSV Columns</h3>
            <p>Your CSV file should have these columns (case-insensitive):</p>
            <ul>
                <?php foreach ($expectedColumns as $col): ?>
                    <li><strong><?= htmlspecialchars($col) ?></strong></li>
                <?php endforeach; ?>
            </ul>
            <p><strong>Note:</strong> The system needs at least 5 columns to process a row.</p>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <h3>Upload Your Xero CSV</h3>
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit" class="btn">Check CSV Format</button>
        </form>

        <?php if (!empty($debugInfo)): ?>
            <div class="debug">
                <h3>🐛 Debug Info</h3>
                <ul>
                    <?php foreach ($debugInfo as $info): ?>
                        <li><?= htmlspecialchars($info) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($csvData): ?>
            <div class="results">
                <h3>✅ Column Check Results</h3>
                <?php foreach ($csvData['columnCheck'] as $check): ?>
                    <div class="check-item"><?= $check ?></div>
                <?php endforeach; ?>
                
                <h3 style="margin-top: 30px;">📊 Your CSV Structure</h3>
                <p><strong>Total Columns:</strong> <?= $csvData['totalColumns'] ?></p>
                
                <h4>Header Row:</h4>
                <table>
                    <tr>
                        <?php foreach ($csvData['header'] as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </table>
                
                <h4>Sample Data (first 5 rows):</h4>
                <table>
                    <tr>
                        <?php foreach ($csvData['header'] as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <?php foreach ($csvData['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= htmlspecialchars($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

