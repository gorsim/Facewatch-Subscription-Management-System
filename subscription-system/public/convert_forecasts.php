<?php
/**
 * Manual Forecast Conversion Tool
 * Converts forecast invoices to actual invoices when they're due
 * This is a temporary manual tool until the cron job is set up on AWS
 */

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/InvoiceAutoGenerationService.php';
require_once __DIR__ . '/../app/Services/InvoiceGenerationService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';

use App\Services\InvoiceAutoGenerationService;
use App\Database;

$db = Database::getInstance();
$service = new InvoiceAutoGenerationService();

// Get the date to check (default to today)
$asOfDate = $_POST['as_of_date'] ?? date('Y-m-d');
$preview = !isset($_POST['confirm']);

// Count how many forecasts are due
$dueCount = $db->fetchOne(
    "SELECT COUNT(*) as count
     FROM invoices i
     JOIN legal_entities le ON i.legal_entity_id = le.id
     WHERE i.next_generation_date <= :as_of_date
     AND i.is_forecast = 1
     AND i.invoice_status = 'forecast'
     AND le.termination_date IS NULL",
    ['as_of_date' => $asOfDate]
)['count'];

$results = null;
if (!$preview) {
    // Run the conversion
    $results = $service->runAutoGeneration($asOfDate);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Convert Forecast Invoices</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
        }
        .warning-box {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 20px 0;
        }
        .success-box {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 20px 0;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="date"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        .btn-success {
            background: #4caf50;
            color: white;
        }
        .btn-secondary {
            background: #757575;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .results-table th,
        .results-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .results-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2196F3;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Convert Forecast Invoices</h1>
        
        <?php if ($results === null): ?>
            <!-- Preview Mode -->
            <div class="info-box">
                <strong>ℹ️ About This Tool</strong><br>
                This tool converts forecast invoices to actual draft invoices when their date arrives.
                Use this until you set up the automated cron job on AWS.
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="as_of_date">Convert forecasts due on or before:</label>
                    <input type="date" id="as_of_date" name="as_of_date" value="<?= htmlspecialchars($asOfDate) ?>">
                </div>
                
                <?php if ($dueCount > 0): ?>
                    <div class="warning-box">
                        <strong>⚠️ Ready to Convert</strong><br>
                        There are <strong><?= $dueCount ?></strong> forecast invoice(s) due for conversion.
                        They will be converted to draft invoices that you can review and issue.
                    </div>
                    <button type="submit" name="confirm" value="1" class="btn btn-success">
                        ✅ Convert <?= $dueCount ?> Forecast Invoice<?= $dueCount != 1 ? 's' : '' ?>
                    </button>
                <?php else: ?>
                    <div class="info-box">
                        <strong>✓ All Clear</strong><br>
                        No forecast invoices are due for conversion as of <?= date('d M Y', strtotime($asOfDate)) ?>.
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-secondary">🔍 Check Different Date</button>
            </form>
            
        <?php else: ?>
            <!-- Results Mode -->
            <div class="success-box">
                <strong>✅ Conversion Complete!</strong><br>
                Successfully processed <?= $results['checked'] ?> invoice(s).
            </div>
            
            <table class="results-table">
                <tr>
                    <th>Metric</th>
                    <th>Count</th>
                </tr>
                <tr>
                    <td>Invoices Checked</td>
                    <td><?= $results['checked'] ?></td>
                </tr>
                <tr>
                    <td>Forecasts Converted to Draft</td>
                    <td><strong><?= $results['converted'] ?></strong></td>
                </tr>
                <tr>
                    <td>New Invoices Generated</td>
                    <td><?= $results['generated'] ?></td>
                </tr>
                <tr>
                    <td>Skipped (Already Generated)</td>
                    <td><?= $results['skipped'] ?></td>
                </tr>
                <tr>
                    <td>Errors</td>
                    <td><?= $results['errors'] ?></td>
                </tr>
            </table>
            
            <a href="convert_forecasts.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block;">
                🔄 Convert More Forecasts
            </a>
        <?php endif; ?>
        
        <a href="index.php?page=invoices" class="back-link">← Back to Invoices</a>
    </div>
</body>
</html>

