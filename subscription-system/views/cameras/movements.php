<?php
/**
 * Camera Movements Report
 * Shows detected camera movements and Xero correction requirements
 */

require_once __DIR__ . '/../../app/Database.php';

use App\Database;

$db = Database::getInstance();

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$requiresXero = isset($_GET['requires_xero']) ? (bool)$_GET['requires_xero'] : null;
$importFilter = $_GET['import_filter'] ?? 'latest'; // Default to latest import only

// Get the latest import session ID
$latestImportId = $db->fetchOne("SELECT MAX(id) as latest_id FROM camera_installation_imports");
$latestImportId = $latestImportId['latest_id'] ?? null;

// Build query
$sql = "
    SELECT
        cm.*,
        cii.import_date,
        COUNT(DISTINCT cmi.id) as total_impacts,
        SUM(CASE WHEN cmi.requires_xero_correction = 1 THEN 1 ELSE 0 END) as xero_corrections_needed,
        SUM(CASE WHEN cmi.xero_corrected = 1 THEN 1 ELSE 0 END) as xero_corrections_done
    FROM camera_movements cm
    LEFT JOIN camera_movement_invoice_impacts cmi ON cm.id = cmi.camera_movement_id
    LEFT JOIN camera_installation_imports cii ON cm.import_session_id = cii.id
    WHERE 1=1
";

$params = [];

if ($status !== 'all') {
    $sql .= " AND cm.xero_correction_status = :status";
    $params['status'] = $status;
}

if ($requiresXero !== null) {
    if ($requiresXero) {
        $sql .= " AND cm.issued_invoices_flagged > 0";
    } else {
        $sql .= " AND cm.issued_invoices_flagged = 0";
    }
}

// Filter by import session
if ($importFilter === 'latest' && $latestImportId) {
    $sql .= " AND cm.import_session_id = :import_session_id";
    $params['import_session_id'] = $latestImportId;
}

$sql .= " GROUP BY cm.id ORDER BY cm.detected_at DESC";

$movements = $db->fetchAll($sql, $params);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'xero') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="camera_movements_xero_corrections_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV Headers
    fputcsv($output, [
        'Movement Date',
        'SAFR Code',
        'Camera Name',
        'From Store',
        'To Store',
        'Invoice Number',
        'Invoice Status',
        'Invoice Date',
        'Action Required'
    ]);

    // Get all movements requiring Xero correction
    $xeroMovements = $db->fetchAll("
        SELECT
            cm.removal_date,
            cm.installation_date,
            cm.safr_code,
            cm.camera_name,
            cm.from_store_name,
            cm.to_store_name,
            cmi.invoice_number,
            cmi.invoice_status,
            cmi.invoice_date,
            cmi.action_notes
        FROM camera_movements cm
        JOIN camera_movement_invoice_impacts cmi ON cm.id = cmi.camera_movement_id
        WHERE cmi.requires_xero_correction = 1
        AND cmi.xero_corrected = 0
        ORDER BY cm.removal_date, cm.safr_code
    ");

    foreach ($xeroMovements as $row) {
        fputcsv($output, [
            $row['removal_date'],
            $row['safr_code'],
            $row['camera_name'] ?? 'N/A',
            $row['from_store_name'],
            $row['to_store_name'],
            $row['invoice_number'],
            $row['invoice_status'],
            $row['invoice_date'],
            $row['action_notes']
        ]);
    }

    fclose($output);
    exit;
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📦 Camera Movements</h2>
        <div>
            <?php if (!empty($movements)): ?>
                <a href="?page=cameras&action=movements&export=xero" class="btn btn-success">
                    <i class="bi bi-download"></i> Export Xero Corrections
                </a>
            <?php endif; ?>
            <a href="?page=imports&action=index" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Imports
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Filter Movements</strong>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="cameras">
                <input type="hidden" name="action" value="movements">

                <div class="col-md-3">
                    <label class="form-label fw-bold">Import Session</label>
                    <select name="import_filter" class="form-select">
                        <option value="all" <?= $importFilter === 'all' ? 'selected' : '' ?>>All Imports</option>
                        <option value="latest" <?= $importFilter === 'latest' ? 'selected' : '' ?>>Latest Import Only</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Xero Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="exported" <?= $status === 'exported' ? 'selected' : '' ?>>Exported</option>
                        <option value="corrected" <?= $status === 'corrected' ? 'selected' : '' ?>>Corrected</option>
                        <option value="not_required" <?= $status === 'not_required' ? 'selected' : '' ?>>Not Required</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Requires Xero Correction</label>
                    <select name="requires_xero" class="form-select">
                        <option value="">All</option>
                        <option value="1" <?= $requiresXero === true ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= $requiresXero === false ? 'selected' : '' ?>>No</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Apply Filters
                    </button>
                    <a href="?page=cameras&action=movements" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Movements Table -->
    <?php if (empty($movements)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No camera movements detected<?= $importFilter === 'latest' ? ' in the latest import' : '' ?>.
            <br><small>Camera movements are automatically detected when you upload incremental camera installation data with removal dates.</small>
        </div>
    <?php else: ?>
        <?php if ($importFilter === 'latest'): ?>
            <div class="alert alert-info mb-3">
                <i class="bi bi-funnel"></i> Showing movements from <strong>Latest Import Only</strong> (Import #<?= $latestImportId ?>)
                <a href="?page=cameras&action=movements" class="ms-2">View All Imports</a>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header">
                <strong><?= count($movements) ?> Camera Movement(s) Detected</strong>
                <?php if ($importFilter === 'latest'): ?>
                    <small class="text-muted ms-2">(from latest import)</small>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Import Date</th>
                                <th>SAFR Code</th>
                                <th>Camera Name</th>
                                <th>From Store</th>
                                <th>To Store</th>
                                <th>Removal Date</th>
                                <th>Installation Date</th>
                                <th>Invoices Affected</th>
                                <th>Xero Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $movement): ?>
                                <tr>
                                    <td>
                                        <?php if ($movement['import_date']): ?>
                                            <span class="badge bg-light text-dark">
                                                <?= date('d/m/Y H:i', strtotime($movement['import_date'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($movement['safr_code']) ?></code></td>
                                    <td><?= htmlspecialchars($movement['camera_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($movement['from_store_name']) ?></td>
                                    <td><?= htmlspecialchars($movement['to_store_name']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($movement['removal_date'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($movement['installation_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= $movement['affected_invoices_count'] ?> total</span>
                                        <?php if ($movement['draft_invoices_updated'] > 0): ?>
                                            <span class="badge bg-success"><?= $movement['draft_invoices_updated'] ?> auto-updated</span>
                                        <?php endif; ?>
                                        <?php if ($movement['issued_invoices_flagged'] > 0): ?>
                                            <span class="badge bg-warning text-dark"><?= $movement['issued_invoices_flagged'] ?> need Xero</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'pending' => 'bg-warning text-dark',
                                            'exported' => 'bg-info',
                                            'corrected' => 'bg-success',
                                            'not_required' => 'bg-secondary'
                                        ];
                                        $badgeClass = $statusBadges[$movement['xero_correction_status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst(str_replace('_', ' ', $movement['xero_correction_status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?page=cameras&action=movement_detail&id=<?= $movement['id'] ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
