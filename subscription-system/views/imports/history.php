<?php
/**
 * Camera Import History
 * Shows all camera file uploads with details
 */

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Database;

$db = Database::getInstance();

// Get all camera imports, newest first
$imports = $db->fetchAll("
    SELECT 
        id,
        import_date,
        filename,
        import_type,
        rows_imported,
        rows_failed,
        movements_detected,
        invoices_auto_updated,
        invoices_flagged_for_xero,
        status,
        error_log
    FROM camera_installation_imports
    ORDER BY import_date DESC
");

require __DIR__ . '/../layouts/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>📁 Camera Import History</h2>
        <a href="?page=imports" class="btn">← Back to Imports</a>
    </div>

    <p style="color: #666; margin-bottom: 20px;">
        This shows all camera CSV files that have been uploaded to the system.
    </p>

    <?php if (empty($imports)): ?>
        <div style="padding: 40px; text-align: center; background: #f5f5f5; border-radius: 8px;">
            <p style="color: #999; font-size: 1.2em;">No camera imports found</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Import Date</th>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Rows Imported</th>
                    <th>Rows Failed</th>
                    <th>Movements</th>
                    <th>Invoices Updated</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($imports as $import): ?>
                    <tr>
                        <td><?= date('d M Y H:i', strtotime($import['import_date'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($import['filename']) ?></strong>
                        </td>
                        <td>
                            <span style="background: <?= $import['import_type'] === 'incremental' ? '#e3f2fd' : '#fff3e0' ?>; 
                                         padding: 4px 8px; border-radius: 4px; font-size: 0.85em;">
                                <?= ucfirst($import['import_type']) ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($import['rows_imported'] > 0): ?>
                                <span style="color: #2e7d32; font-weight: bold;">
                                    ✓ <?= $import['rows_imported'] ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">0</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($import['rows_failed'] > 0): ?>
                                <span style="color: #c62828; font-weight: bold;">
                                    ✗ <?= $import['rows_failed'] ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">0</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($import['movements_detected'] > 0): ?>
                                <span style="color: #1976d2; font-weight: bold;">
                                    🔄 <?= $import['movements_detected'] ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php 
                            $totalUpdated = ($import['invoices_auto_updated'] ?? 0) + ($import['invoices_flagged_for_xero'] ?? 0);
                            if ($totalUpdated > 0): 
                            ?>
                                <span style="color: #f57c00; font-weight: bold;">
                                    📄 <?= $totalUpdated ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($import['status'] === 'success'): ?>
                                <span style="background: #e8f5e9; color: #2e7d32; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">
                                    ✓ Success
                                </span>
                            <?php elseif ($import['status'] === 'partial'): ?>
                                <span style="background: #fff3e0; color: #e65100; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">
                                    ⚠ Partial
                                </span>
                            <?php else: ?>
                                <span style="background: #ffebee; color: #c62828; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">
                                    ✗ Failed
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?page=imports&action=view_import&id=<?= $import['id'] ?>" class="btn btn-sm">
                                View Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

