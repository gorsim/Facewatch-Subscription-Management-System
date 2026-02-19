<?php
/**
 * Camera Movement Detail View
 * Shows detailed information about a specific camera movement
 */

require_once __DIR__ . '/../../app/Database.php';

use App\Database;

$db = Database::getInstance();

// Get movement ID from URL
$movementId = $_GET['id'] ?? null;

if (!$movementId) {
    header('Location: ?page=cameras&action=movements');
    exit;
}

// Get movement details
$movement = $db->fetchOne("
    SELECT cm.*
    FROM camera_movements cm
    WHERE cm.id = :id
", ['id' => $movementId]);

if (!$movement) {
    header('Location: ?page=cameras&action=movements');
    exit;
}

// Get affected invoices
$invoiceImpacts = $db->fetchAll("
    SELECT 
        cmi.*,
        i.invoice_number,
        i.invoice_date,
        i.invoice_status,
        i.invoice_amount,
        le.legal_entity_name
    FROM camera_movement_invoice_impacts cmi
    JOIN invoices i ON cmi.invoice_id = i.id
    JOIN legal_entities le ON i.legal_entity_id = le.id
    WHERE cmi.camera_movement_id = :movement_id
    ORDER BY i.invoice_date DESC
", ['movement_id' => $movementId]);

require __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="?page=cameras&action=movements">Camera Transfers</a></li>
            <li class="breadcrumb-item active">Movement Details</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-arrow-left-right"></i> Camera Movement Details
        </h1>
        <a href="?page=cameras&action=movements" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Movements
        </a>
    </div>

    <!-- Movement Summary Card -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <strong>Movement Summary</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Camera Information</h5>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">SAFR Code:</th>
                            <td><code><?= htmlspecialchars($movement['safr_code']) ?></code></td>
                        </tr>
                        <tr>
                            <th>Camera Name:</th>
                            <td><?= htmlspecialchars($movement['camera_name'] ?? 'N/A') ?></td>
                        </tr>
                    </table>

                    <h5 class="mt-4">Movement Details</h5>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">From Store:</th>
                            <td><?= htmlspecialchars($movement['from_store_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Removal Date:</th>
                            <td><?= date('d/m/Y', strtotime($movement['removal_date'])) ?></td>
                        </tr>
                        <tr>
                            <th>To Store:</th>
                            <td><?= htmlspecialchars($movement['to_store_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Installation Date:</th>
                            <td><?= date('d/m/Y', strtotime($movement['installation_date'])) ?></td>
                        </tr>
                    </table>
                </div>

                <div class="col-md-6">
                    <h5>Detection Information</h5>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Detected At:</th>
                            <td><?= date('d/m/Y H:i:s', strtotime($movement['detected_at'])) ?></td>
                        </tr>
                        <tr>
                            <th>Detected By:</th>
                            <td><?= htmlspecialchars($movement['detected_by'] ?? 'System') ?></td>
                        </tr>
                    </table>

                    <h5 class="mt-4">Invoice Impact</h5>
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Total Invoices Affected:</th>
                            <td><span class="badge bg-info"><?= $movement['affected_invoices_count'] ?></span></td>
                        </tr>
                        <tr>
                            <th>Draft Invoices Updated:</th>
                            <td><span class="badge bg-success"><?= $movement['draft_invoices_updated'] ?></span></td>
                        </tr>
                        <tr>
                            <th>Issued Invoices Flagged:</th>
                            <td><span class="badge bg-warning text-dark"><?= $movement['issued_invoices_flagged'] ?></span></td>
                        </tr>
                        <tr>
                            <th>Xero Correction Status:</th>
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
                        </tr>
                    </table>

                    <?php if (!empty($movement['xero_correction_notes'])): ?>
                        <h5 class="mt-4">Xero Correction Notes</h5>
                        <div class="alert alert-info">
                            <?= nl2br(htmlspecialchars($movement['xero_correction_notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Affected Invoices -->
    <div class="card">
        <div class="card-header">
            <strong>Affected Invoices (<?= count($invoiceImpacts) ?>)</strong>
        </div>
        <div class="card-body p-0">
            <?php if (empty($invoiceImpacts)): ?>
                <div class="p-3 text-muted">
                    <i class="bi bi-info-circle"></i> No invoice impacts recorded for this movement.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice Number</th>
                                <th>Legal Entity</th>
                                <th>Invoice Date</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Old Store</th>
                                <th>New Store</th>
                                <th>Action Taken</th>
                                <th>Xero Correction</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoiceImpacts as $impact): ?>
                                <tr>
                                    <td>
                                        <a href="?page=invoices&action=view&id=<?= $impact['invoice_id'] ?>">
                                            <?= htmlspecialchars($impact['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($impact['legal_entity_name']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($impact['invoice_date'])) ?></td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'draft' => 'bg-secondary',
                                            'issued' => 'bg-primary',
                                            'paid' => 'bg-success',
                                            'overdue' => 'bg-danger',
                                            'cancelled' => 'bg-dark'
                                        ];
                                        $badgeClass = $statusBadges[$impact['invoice_status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst($impact['invoice_status']) ?>
                                        </span>
                                    </td>
                                    <td>£<?= number_format($impact['invoice_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($impact['old_store_name']) ?></td>
                                    <td><?= htmlspecialchars($impact['new_store_name']) ?></td>
                                    <td>
                                        <?php
                                        $actionBadges = [
                                            'auto_updated' => 'bg-success',
                                            'flagged_for_xero' => 'bg-warning text-dark',
                                            'no_action' => 'bg-secondary'
                                        ];
                                        $badgeClass = $actionBadges[$impact['action_taken']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst(str_replace('_', ' ', $impact['action_taken'])) ?>
                                        </span>
                                        <?php if (!empty($impact['action_notes'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($impact['action_notes']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($impact['requires_xero_correction']): ?>
                                            <?php if ($impact['xero_corrected']): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle"></i> Corrected
                                                </span>
                                                <br><small class="text-muted">
                                                    <?= date('d/m/Y', strtotime($impact['xero_corrected_at'])) ?>
                                                    by <?= htmlspecialchars($impact['xero_corrected_by']) ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-exclamation-triangle"></i> Required
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Required</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
