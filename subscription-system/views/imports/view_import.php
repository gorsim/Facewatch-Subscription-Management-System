<?php
/**
 * View Camera Import Details
 * Shows detailed information about a specific camera import
 */

use App\Database;

$pageTitle = 'Import Details';
$page = 'imports';

$db = Database::getInstance();

// Get import ID from URL
$importId = $_GET['id'] ?? null;

if (!$importId) {
    $_SESSION['error'] = 'Import ID is required';
    header('Location: ?page=imports&action=history');
    exit;
}

// Get import details
$import = $db->fetchOne("
    SELECT *
    FROM camera_installation_imports
    WHERE id = :id
", ['id' => $importId]);

if (!$import) {
    $_SESSION['error'] = 'Import not found';
    header('Location: ?page=imports&action=history');
    exit;
}

// Parse error log if it exists
$errors = [];
if (!empty($import['error_log'])) {
    $errors = json_decode($import['error_log'], true) ?? [];
}

// Get camera movements detected in this import
$movements = $db->fetchAll("
    SELECT 
        cm.*,
        fs.store_name as from_store_name,
        ts.store_name as to_store_name
    FROM camera_movements cm
    LEFT JOIN stores fs ON cm.from_store_id = fs.id
    LEFT JOIN stores ts ON cm.to_store_id = ts.id
    WHERE cm.import_session_id = :import_id
    ORDER BY cm.detected_at DESC
", ['import_id' => $importId]);

require __DIR__ . '/../layouts/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>📁 Import Details</h2>
        <a href="?page=imports&action=history" class="btn">← Back to History</a>
    </div>

    <!-- Import Summary -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="margin-top: 0;">Import Summary</h3>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div>
                <strong>Filename:</strong><br>
                <?= htmlspecialchars($import['filename']) ?>
            </div>
            <div>
                <strong>Import Date:</strong><br>
                <?= date('d M Y H:i:s', strtotime($import['import_date'])) ?>
            </div>
            <div>
                <strong>Import Type:</strong><br>
                <span style="background: <?= $import['import_type'] === 'incremental' ? '#e3f2fd' : '#fff3e0' ?>; 
                             padding: 4px 8px; border-radius: 4px; font-size: 0.9em;">
                    <?= ucfirst($import['import_type']) ?>
                </span>
            </div>
            <div>
                <strong>Status:</strong><br>
                <?php if ($import['status'] === 'success'): ?>
                    <span style="background: #e8f5e9; color: #2e7d32; padding: 4px 12px; border-radius: 12px; font-weight: bold;">
                        ✓ Success
                    </span>
                <?php elseif ($import['status'] === 'partial'): ?>
                    <span style="background: #fff3e0; color: #e65100; padding: 4px 12px; border-radius: 12px; font-weight: bold;">
                        ⚠ Partial Success
                    </span>
                <?php else: ?>
                    <span style="background: #ffebee; color: #c62828; padding: 4px 12px; border-radius: 12px; font-weight: bold;">
                        ✗ Failed
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px;">
        <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold; color: #2e7d32;"><?= $import['rows_imported'] ?></div>
            <div style="color: #666; margin-top: 5px;">Rows Imported</div>
        </div>
        <div style="background: #ffebee; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold; color: #c62828;"><?= $import['rows_failed'] ?></div>
            <div style="color: #666; margin-top: 5px;">Rows Failed</div>
        </div>
        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold; color: #1976d2;"><?= $import['movements_detected'] ?? 0 ?></div>
            <div style="color: #666; margin-top: 5px;">Movements Detected</div>
        </div>
        <div style="background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold; color: #e65100;">
                <?= ($import['invoices_auto_updated'] ?? 0) + ($import['invoices_flagged_for_xero'] ?? 0) ?>
            </div>
            <div style="color: #666; margin-top: 5px;">Invoices Affected</div>
        </div>
    </div>

    <!-- Camera Movements -->
    <?php if (!empty($movements)): ?>
        <h3>🔄 Camera Movements Detected</h3>
        <p style="color: #666; margin-bottom: 15px;">
            This import detected <?= count($movements) ?> camera movement(s) between stores.
        </p>
        
        <table style="margin-bottom: 30px;">
            <thead>
                <tr>
                    <th>SAFR Code</th>
                    <th>Camera Name</th>
                    <th>From Store</th>
                    <th>To Store</th>
                    <th>Removal Date</th>
                    <th>Installation Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movements as $movement): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($movement['safr_code']) ?></strong></td>
                        <td><?= htmlspecialchars($movement['camera_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($movement['from_store_name']) ?></td>
                        <td><?= htmlspecialchars($movement['to_store_name']) ?></td>
                        <td><?= date('d M Y', strtotime($movement['removal_date'])) ?></td>
                        <td><?= date('d M Y', strtotime($movement['installation_date'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
        <h3 style="color: #c62828;">⚠ Errors</h3>
        <div style="background: #ffebee; padding: 15px; border-radius: 8px; border-left: 4px solid #c62828;">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li style="margin-bottom: 8px; color: #c62828;">
                        <?= htmlspecialchars($error) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

