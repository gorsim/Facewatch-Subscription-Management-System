<?php
/**
 * Import Data Page
 */

use App\Services\SubscriberImporter;
use App\Services\StoreImporter;
use App\Services\CameraInstallationImporter;

$pageTitle = 'Import Data';
$page = 'import';

$type = $_GET['type'] ?? 'subscribers';
$message = '';
$errors = [];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    error_log("Import: POST request received. Type = {$type}");
    error_log("Import: Files = " . print_r($_FILES, true));
    error_log("Import: POST = " . print_r($_POST, true));

    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $file = $_FILES['import_file'];
    $filename = basename($file['name']);
    $targetPath = $uploadDir . time() . '_' . $filename;

    error_log("Import: Attempting to upload file to {$targetPath}");

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        error_log("Import: File uploaded successfully");
        try {
            if ($type === 'subscribers') {
                $importer = new SubscriberImporter();
                $success = $importer->import($targetPath);

                if ($success) {
                    $message = "Successfully imported {$importer->getImported()} new subscribers and updated {$importer->getUpdated()} existing subscribers.";
                } else {
                    $errors = $importer->getErrors();
                }
            } elseif ($type === 'stores') {
                $importer = new StoreImporter();
                $success = $importer->import($targetPath);

                if ($success) {
                    $message = "Successfully imported {$importer->getImported()} new stores and updated {$importer->getUpdated()} existing stores.";
                } else {
                    $errors = $importer->getErrors();
                }
            } elseif ($type === 'camera_installations') {
                $importer = new CameraInstallationImporter();
                $success = $importer->import($targetPath);

                if ($success) {
                    $imported = $importer->getImported();
                    $updated = $importer->getUpdated();

                    if ($imported > 0 && $updated > 0) {
                        $message = "Successfully added {$imported} new camera(s) and updated {$updated} existing camera(s).";
                    } elseif ($imported > 0) {
                        $message = "Successfully added {$imported} new camera(s).";
                    } elseif ($updated > 0) {
                        $message = "Successfully updated {$updated} existing camera(s).";
                    } else {
                        $message = "No changes made.";
                    }

                    // Add movement detection info
                    if ($importer->getMovementsDetected() > 0) {
                        $message .= "<br><strong>📦 Camera Movements Detected: {$importer->getMovementsDetected()}</strong>";
                        $message .= "<br>• {$importer->getInvoicesAutoUpdated()} draft invoices automatically updated";
                        if ($importer->getInvoicesFlaggedForXero() > 0) {
                            $message .= "<br>• <span class='text-warning'>{$importer->getInvoicesFlaggedForXero()} issued invoices flagged for Xero correction</span>";
                            $message .= "<br><a href='?page=cameras&action=movements' class='btn btn-sm btn-warning mt-2'><i class='bi bi-exclamation-triangle'></i> View Xero Corrections Required</a>";
                        }
                    }
                } else {
                    $errors = $importer->getErrors();
                }
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        // Clean up uploaded file
        unlink($targetPath);
    } else {
        $errors[] = "Failed to upload file";
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Import Data</h2>
        <a href="?page=cameras&action=movements" class="btn btn-info">
            <i class="bi bi-arrow-left-right"></i> View Camera Movements
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="background: #f8d7da; border: 2px solid #f5c6cb; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
        <h3 style="margin-top: 0; color: #721c24;">⚠️ Import Errors (<?= count($errors) ?>)</h3>
        <p style="margin-bottom: 10px;">The following rows could not be imported:</p>
        <ul style="margin: 10px 0; max-height: 400px; overflow-y: auto; background: white; padding: 15px; border-radius: 3px;">
            <?php foreach ($errors as $error): ?>
                <li style="margin: 5px 0; padding: 5px; border-bottom: 1px solid #f5c6cb;"><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <p style="margin-top: 15px; font-weight: bold; color: #721c24;">
            💡 Tip: Check that the "Xero Company Name" in your CSV exactly matches the "Xero Company Name" field in your Legal Entities.
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Import Data</h2>
    
    <div style="margin-bottom: 20px;">
        <a href="?page=import&type=subscribers" class="btn <?= $type === 'subscribers' ? 'btn-success' : '' ?>">Legal Entities</a>
        <a href="?page=import&type=stores" class="btn <?= $type === 'stores' ? 'btn-success' : '' ?>">Stores</a>
        <a href="?page=import&type=camera_installations" class="btn <?= $type === 'camera_installations' ? 'btn-success' : '' ?>">Camera Installations</a>
    </div>

    <?php if ($type === 'subscribers'): ?>
        <h3>Import Legal Entities</h3>
        <p>Upload a CSV file containing legal entity information. All entities are imported with <strong>default pricing tiers</strong>.</p>
        <p style="color: #666; font-size: 0.9em;">💡 To configure custom pricing for an entity, use the admin UI after import.</p>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <strong>Required CSV Columns:</strong>
            <ul style="margin: 10px 0;">
                <li><strong>Legal Entity Name</strong> OR <strong>Subscriber Name</strong> - Customer name (required)</li>
            </ul>
            <strong>Optional Columns:</strong>
            <ul style="margin: 10px 0;">
                <li><strong>Legal Entity ID</strong> - Unique identifier</li>
                <li><strong>Xero Company Name</strong> - Name used in Xero</li>
                <li><strong>Payment Frequency</strong> - Annual, Quarterly, or Monthly (defaults to Annual)</li>
                <li><strong>Pricing Type</strong> - "default" or "custom" (defaults to "default")</li>
                <li><strong>Installation Date</strong> - Date format: YYYY-MM-DD or DD/MM/YYYY</li>
                <li><strong>Category</strong> - Business category</li>
                <li><strong>Sales Credit</strong> - Sales person credit</li>
            </ul>
            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin-top: 10px;">
                <strong>⚠️ Note:</strong> The old "Main Cam Rate" and "Additional Cam Rate" fields are no longer used.
                All entities now use volume-based pricing tiers. See <a href="?page=admin&action=pricing">Pricing Tiers</a>.
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="import_file">Select CSV File</label>
                <input type="file" id="import_file" name="import_file" accept=".csv" required>
            </div>

            <button type="submit" class="btn btn-success">Import Legal Entities</button>
        </form>

    <?php elseif ($type === 'stores'): ?>
        <h3>Import Stores</h3>
        <p>Upload a CSV file containing store data linked to legal entities.</p>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <strong>Required CSV Columns:</strong>
            <ul style="margin-top: 10px;">
                <li><strong>Legal Entity ID</strong> - e.g., LE0001 (must match existing legal entity)</li>
                <li><strong>Store ID</strong> - Unique store identifier (e.g., LE0001-001)</li>
                <li><strong>Store Name</strong> - Name of the store</li>
            </ul>
            <strong>Optional Columns:</strong>
            <ul style="margin-top: 10px;">
                <li>Legal Entity Name (for reference only)</li>
                <li>Installation Date (format: YYYY-MM-DD or DD/MM/YYYY)</li>
                <li>Termination Date (format: YYYY-MM-DD or DD/MM/YYYY)</li>
                <li>Category (e.g., Retail, Wholesale)</li>
            </ul>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="import_file">Select CSV File</label>
                <input type="file" id="import_file" name="import_file" accept=".csv" required>
            </div>

            <button type="submit" class="btn btn-success">Import Stores</button>
        </form>

    <?php elseif ($type === 'camera_installations'): ?>
        <h3>Import Camera Installations</h3>
        <p>Upload a CSV file containing incremental camera installation data.</p>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <strong>Required CSV Columns:</strong>
            <ul style="margin-top: 10px;">
                <li><strong>Store ID</strong> - Must match existing store (e.g., LE0001-001)</li>
                <li><strong>Installation Date</strong> - When camera was installed (YYYY-MM-DD or DD/MM/YYYY)</li>
                <li><strong>Camera Type</strong> - Either "main" or "additional"</li>
            </ul>
            <strong>Optional Columns:</strong>
            <ul style="margin-top: 10px;">
                <li><strong>Camera Name</strong> - Friendly name for the camera (e.g., "Front Entrance", "Checkout Area")</li>
                <li><strong>SAFR Code</strong> - SAFR system code for the camera</li>
                <li>Store Name (for reference only)</li>
                <li>Invoice Number (links to invoice)</li>
                <li>Removal Date (if camera was removed)</li>
                <li>Notes</li>
            </ul>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="import_file">Select CSV File</label>
                <input type="file" id="import_file" name="import_file" accept=".csv" required>
            </div>

            <button type="submit" class="btn btn-success">Import Camera Installations</button>
        </form>

    <?php endif; ?>
</div>

<div class="card">
    <h3>Import History</h3>
    <?php
    use App\Database;
    $db = Database::getInstance();

    if ($type === 'subscribers') {
        $imports = $db->fetchAll("SELECT * FROM legal_entity_imports ORDER BY import_date DESC LIMIT 10");
    } elseif ($type === 'stores') {
        $imports = $db->fetchAll("SELECT * FROM store_imports ORDER BY import_date DESC LIMIT 10");
    } elseif ($type === 'camera_installations') {
        $imports = $db->fetchAll("SELECT * FROM camera_installation_imports ORDER BY import_date DESC LIMIT 10");
    } else {
        $imports = $db->fetchAll("SELECT * FROM camera_imports ORDER BY import_date DESC LIMIT 10");
    }
    ?>
    
    <?php if (!empty($imports)): ?>
        <table>
            <thead>
                <tr>
                    <th>Import Date</th>
                    <th>Filename</th>
                    <?php if ($type === 'cameras'): ?>
                        <th>Snapshot Date</th>
                    <?php endif; ?>
                    <th><?= $type === 'subscribers' ? 'New' : 'Imported' ?></th>
                    <th><?= $type === 'subscribers' ? 'Updated' : 'Skipped/Updated' ?></th>
                    <?php if ($type === 'camera_installations'): ?>
                        <th>Movements</th>
                    <?php endif; ?>
                    <th>Errors</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($imports as $import): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($import['import_date'])) ?></td>
                    <td><?= htmlspecialchars($import['filename']) ?></td>
                    <?php if ($type === 'cameras'): ?>
                        <td>
                            <?php
                            // Extract snapshot date from filename if it contains it
                            if (preg_match('/\(Snapshot: ([^)]+)\)/', $import['filename'], $matches)) {
                                echo htmlspecialchars($matches[1]);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                    <?php endif; ?>
                    <td><?= $import['rows_imported'] ?? $import['records_imported'] ?? 0 ?></td>
                    <td><?= $import['rows_failed'] ?? $import['records_skipped'] ?? $import['records_updated'] ?? 0 ?></td>
                    <?php if ($type === 'camera_installations'): ?>
                        <td>
                            <?php if (isset($import['movements_detected']) && $import['movements_detected'] > 0): ?>
                                <span class="badge bg-info"><?= $import['movements_detected'] ?></span>
                                <?php if ($import['invoices_flagged_for_xero'] > 0): ?>
                                    <span class="badge bg-warning text-dark" title="Requires Xero correction">
                                        <i class="bi bi-exclamation-triangle"></i> <?= $import['invoices_flagged_for_xero'] ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td>
                        <?php
                        $errors = json_decode($import['error_log'] ?? $import['errors'] ?? '[]', true) ?? [];
                        echo count($errors);
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No import history yet.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

