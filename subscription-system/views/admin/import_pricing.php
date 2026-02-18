<?php
/**
 * Import Pricing Tiers from CSV
 */

use App\Database;

$pageTitle = 'Import Pricing Tiers';
$page = 'admin';

$db = Database::getInstance();

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        $file = $_FILES['csv_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }
        
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not open file');
        }
        
        // Read header row
        $header = fgetcsv($handle);

        // Trim whitespace from header columns
        $header = array_map('trim', $header);

        // Expected columns: min_cameras, max_cameras, price_per_annum, price_per_quarter, price_per_month, effective_date, notes
        $expectedColumns = ['min_cameras', 'max_cameras', 'price_per_annum', 'price_per_quarter', 'price_per_month', 'effective_date', 'notes'];

        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Trim whitespace from all values
                $row = array_map('trim', $row);

                // Map row to columns
                if (count($header) !== count($row)) {
                    $errors[] = "Skipping row: Column count mismatch (expected " . count($header) . ", got " . count($row) . ")";
                    continue;
                }

                $data = array_combine($header, $row);

                // Validate required fields
                if (empty($data['min_cameras']) || empty($data['price_per_annum']) || empty($data['effective_date'])) {
                    $errors[] = "Skipping row: Missing required fields (min_cameras, price_per_annum, or effective_date)";
                    continue;
                }

                // Convert date format if needed (handle both DD/MM/YYYY and YYYY-MM-DD)
                $effectiveDate = $data['effective_date'];
                if (strpos($effectiveDate, '/') !== false) {
                    // Convert DD/MM/YYYY or MM/DD/YYYY to YYYY-MM-DD
                    $dateParts = explode('/', $effectiveDate);
                    if (count($dateParts) === 3) {
                        // Assume DD/MM/YYYY format
                        $effectiveDate = $dateParts[2] . '-' . str_pad($dateParts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($dateParts[0], 2, '0', STR_PAD_LEFT);
                    }
                }

                // Debug: Log what we're about to insert
                error_log("Importing row: min_cameras={$data['min_cameras']}, price_per_annum={$data['price_per_annum']}, effective_date={$effectiveDate}");

                // Insert pricing tier (strip commas from numbers before converting)
                $db->insert('camera_pricing', [
                    'min_cameras' => (int)str_replace(',', '', $data['min_cameras']),
                    'max_cameras' => !empty($data['max_cameras']) ? (int)str_replace(',', '', $data['max_cameras']) : null,
                    'price_per_annum' => (float)str_replace(',', '', $data['price_per_annum']),
                    'price_per_quarter' => !empty($data['price_per_quarter']) ? (float)str_replace(',', '', $data['price_per_quarter']) : null,
                    'price_per_month' => !empty($data['price_per_month']) ? (float)str_replace(',', '', $data['price_per_month']) : null,
                    'effective_date' => $effectiveDate,
                    'notes' => $data['notes'] ?? ''
                ]);

                $imported++;
            } catch (Exception $e) {
                $errors[] = "Error importing row: " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        $message = "Successfully imported $imported pricing tiers!";
        if (!empty($errors)) {
            $message .= " " . count($errors) . " errors occurred.";
        }
        
        $_SESSION['success'] = $message;
        if (!empty($errors)) {
            $_SESSION['import_errors'] = $errors;
        }
        
        header('Location: ?page=admin&action=import_pricing');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Import failed: ' . $e->getMessage();
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div class="card">
        <h2>📥 Import Pricing Tiers from CSV</h2>
        
        <?php if (isset($_SESSION['import_errors'])): ?>
            <div class="alert alert-warning">
                <strong>Import Errors:</strong>
                <ul>
                    <?php foreach ($_SESSION['import_errors'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['import_errors']); ?>
        <?php endif; ?>
        
        <div class="info-box" style="background: #e3f2fd; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h3>📋 CSV Format Requirements</h3>
            <p><strong>Required columns (in this order):</strong></p>
            <ol>
                <li><code>min_cameras</code> - Minimum cameras in tier (e.g., 1, 50, 100)</li>
                <li><code>max_cameras</code> - Maximum cameras in tier (leave empty for unlimited)</li>
                <li><code>price_per_annum</code> - Annual price per camera</li>
                <li><code>price_per_quarter</code> - Quarterly price per camera (optional)</li>
                <li><code>price_per_month</code> - Monthly price per camera (optional)</li>
                <li><code>effective_date</code> - Date this pricing starts (YYYY-MM-DD format)</li>
                <li><code>notes</code> - Optional notes (e.g., "2025 pricing - 3% increase")</li>
            </ol>
            
            <h4>📝 Example CSV:</h4>
            <pre style="background: white; padding: 10px; border-radius: 4px; overflow-x: auto;">min_cameras,max_cameras,price_per_annum,price_per_quarter,price_per_month,effective_date,notes
1,49,3860,965,322,2024-01-01,2024 base pricing
50,99,3700,925,308,2024-01-01,2024 base pricing
1,49,3976,994,331,2025-01-01,2025 pricing - 3% increase
50,99,3811,953,318,2025-01-01,2025 pricing - 3% increase</pre>
        </div>
        
        <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
            <div class="form-group">
                <label for="csv_file">Select CSV File:</label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv" required class="form-control">
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">📥 Import Pricing Tiers</button>
                <a href="?page=admin&action=pricing" class="btn">Cancel</a>
            </div>
        </form>
    </div>
    
    <div class="card" style="margin-top: 20px;">
        <h3>📊 Current Pricing Tiers</h3>
        <?php
        $tiers = $db->fetchAll("
            SELECT * FROM camera_pricing 
            ORDER BY effective_date DESC, min_cameras ASC
        ");
        
        if (empty($tiers)): ?>
            <p style="color: #999;">No pricing tiers found. Import some to get started!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Tier</th>
                        <th>Annual</th>
                        <th>Quarterly</th>
                        <th>Monthly</th>
                        <th>Effective Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tiers as $tier): ?>
                        <tr>
                            <td><?= $tier['min_cameras'] ?>-<?= $tier['max_cameras'] ?? '∞' ?> cameras</td>
                            <td>£<?= number_format($tier['price_per_annum'], 2) ?></td>
                            <td><?= $tier['price_per_quarter'] ? '£' . number_format($tier['price_per_quarter'], 2) : '-' ?></td>
                            <td><?= $tier['price_per_month'] ? '£' . number_format($tier['price_per_month'], 2) : '-' ?></td>
                            <td><?= date('d M Y', strtotime($tier['effective_date'])) ?></td>
                            <td><?= htmlspecialchars($tier['notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

