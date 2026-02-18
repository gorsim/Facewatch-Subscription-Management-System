<?php
/**
 * Invoice Clustering View
 * Shows all invoices for a legal entity in a timeline format
 */

use App\Database;
use App\Services\InvoiceAutoGenerationService;

$pageTitle = 'Invoice Timeline';
$page = 'invoices';

$db = Database::getInstance();

// Get legal entity ID from URL
$legalEntityId = $_GET['legal_entity_id'] ?? null;
$specificInvoiceId = $_GET['invoice_id'] ?? null;

if (!$legalEntityId) {
    $_SESSION['error'] = 'Legal entity ID required';
    header('Location: ?page=invoices');
    exit;
}

// Get legal entity details
$entity = $db->fetchOne(
    "SELECT * FROM legal_entities WHERE id = :id",
    ['id' => $legalEntityId]
);

if (!$entity) {
    $_SESSION['error'] = 'Legal entity not found';
    header('Location: ?page=invoices');
    exit;
}

// If a specific invoice is requested, find its parent (if it's a forecast) or use it as the parent
$filterParentId = null;
if ($specificInvoiceId) {
    $specificInvoice = $db->fetchOne(
        "SELECT id, parent_invoice_id FROM invoices WHERE id = :id",
        ['id' => $specificInvoiceId]
    );
    if ($specificInvoice) {
        // If this invoice has a parent, use the parent. Otherwise, use this invoice as the parent.
        $filterParentId = $specificInvoice['parent_invoice_id'] ?? $specificInvoice['id'];
    }
}

// Get all invoices for this legal entity (including forecasts)
// If filtering by specific invoice, only get that chain
if ($filterParentId) {
    $invoices = $db->fetchAll("
        SELECT
            i.*,
            COUNT(ica.id) as camera_count
        FROM invoices i
        LEFT JOIN invoice_camera_allocations ica ON i.id = ica.invoice_id AND ica.removed_date IS NULL
        WHERE i.legal_entity_id = :legal_entity_id
          AND (i.id = :parent_id1 OR i.parent_invoice_id = :parent_id2)
        GROUP BY i.id
        ORDER BY i.invoice_date ASC
    ", [
        'legal_entity_id' => $legalEntityId,
        'parent_id1' => $filterParentId,
        'parent_id2' => $filterParentId
    ]);
} else {
    $invoices = $db->fetchAll("
        SELECT
            i.*,
            COUNT(ica.id) as camera_count
        FROM invoices i
        LEFT JOIN invoice_camera_allocations ica ON i.id = ica.invoice_id AND ica.removed_date IS NULL
        WHERE i.legal_entity_id = :legal_entity_id
        GROUP BY i.id
        ORDER BY i.invoice_date ASC
    ", ['legal_entity_id' => $legalEntityId]);
}

// Group invoices by parent chain
$invoiceChains = [];
$orphanInvoices = [];

foreach ($invoices as $invoice) {
    if ($invoice['parent_invoice_id']) {
        // This is a forecast - add to parent's chain
        $parentId = $invoice['parent_invoice_id'];
        if (!isset($invoiceChains[$parentId])) {
            $invoiceChains[$parentId] = [];
        }
        $invoiceChains[$parentId][] = $invoice;
    } else {
        // This is a parent invoice
        if (!isset($invoiceChains[$invoice['id']])) {
            $invoiceChains[$invoice['id']] = [];
        }
        // Add the parent itself at the beginning
        array_unshift($invoiceChains[$invoice['id']], $invoice);
    }
}

// Calculate totals
$totalActual = 0;
$totalForecast = 0;
$actualCount = 0;
$forecastCount = 0;

foreach ($invoices as $invoice) {
    if ($invoice['is_forecast']) {
        $totalForecast += $invoice['invoice_amount'];
        $forecastCount++;
    } else {
        $totalActual += $invoice['invoice_amount'];
        $actualCount++;
    }
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1>📅 Invoice Timeline</h1>
            <p style="color: #666; margin-top: 5px;">
                For: <strong><?= htmlspecialchars($entity['legal_entity_name']) ?></strong>
                <?php if ($specificInvoiceId): ?>
                    <span style="color: #1976d2;"> • Showing specific invoice chain</span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <?php if ($forecastCount == 0): ?>
                <div style="padding: 8px 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; font-size: 0.9em;">
                    ⚠️ No forecast invoices found. Click <strong>"Recalculate All & Generate Forecasts"</strong> on the Invoices page to generate them.
                </div>
            <?php endif; ?>
            <?php if ($specificInvoiceId): ?>
                <a href="?page=invoices&action=cluster&legal_entity_id=<?= $legalEntityId ?>" class="btn">📊 View All Invoices</a>
            <?php endif; ?>
            <a href="?page=subscribers&action=view&id=<?= $legalEntityId ?>" class="btn">← Back to Entity</a>
            <a href="?page=invoices" class="btn">All Invoices</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div class="card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2em; font-weight: bold; color: #333;"><?= $actualCount ?></div>
            <div style="color: #666; margin-top: 5px;">Actual Invoices</div>
        </div>
        <div class="card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2em; font-weight: bold; color: #5cb85c;">£<?= number_format($totalActual, 0) ?></div>
            <div style="color: #666; margin-top: 5px;">Total Actual</div>
        </div>
        <div class="card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2em; font-weight: bold; color: #1976d2;"><?= $forecastCount ?></div>
            <div style="color: #666; margin-top: 5px;">🔮 Forecast Invoices</div>
        </div>
        <div class="card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2em; font-weight: bold; color: #1976d2;">£<?= number_format($totalForecast, 0) ?></div>
            <div style="color: #666; margin-top: 5px;">🔮 Total Forecast (3 years)</div>
        </div>
    </div>

    <?php if (empty($invoiceChains)): ?>
        <div class="card">
            <p>No invoices found for this legal entity.</p>
        </div>
    <?php else: ?>
        <?php foreach ($invoiceChains as $parentId => $chain): ?>
            <?php if (empty($chain)) continue; ?>
            <?php
            $parent = $chain[0];
            $forecasts = array_slice($chain, 1);
            $chainTotal = array_sum(array_column($chain, 'invoice_amount'));
            ?>

            <div class="card" style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 20px;">
                    Invoice Chain: <?= htmlspecialchars($parent['invoice_number']) ?>
                    <span style="font-size: 0.8em; color: #666; font-weight: normal;">
                        (<?= $parent['camera_count'] ?> cameras, Total 3-year value: £<?= number_format($chainTotal, 2) ?>)
                    </span>
                </h3>

                <!-- Timeline -->
                <div style="position: relative; padding: 20px 0;">
                    <!-- Timeline line -->
                    <div style="position: absolute; left: 50px; top: 0; bottom: 0; width: 2px; background: #ddd;"></div>

                    <?php foreach ($chain as $index => $invoice):
                        $isForecast = $invoice['is_forecast'];
                        $statusColor = $isForecast ? '#1976d2' : ($invoice['invoice_status'] === 'reconciled_to_xero' ? '#27ae60' : '#3498db');
                        $statusIcon = $isForecast ? '🔮' : ($invoice['invoice_status'] === 'reconciled_to_xero' ? '✅' : '📤');
                        $statusLabel = $isForecast ? 'Forecast Year ' . $invoice['forecast_year'] : ucfirst(str_replace('_', ' ', $invoice['invoice_status']));
                    ?>

                    <div style="position: relative; padding: 15px 0 15px 80px; min-height: 80px;">
                        <!-- Timeline dot -->
                        <div style="position: absolute; left: 41px; top: 25px; width: 18px; height: 18px; border-radius: 50%; background: <?= $statusColor ?>; border: 3px solid white; box-shadow: 0 0 0 2px <?= $statusColor ?>;"></div>

                        <!-- Invoice card -->
                        <div style="background: <?= $isForecast ? '#f0f8ff' : 'white' ?>; border: 2px solid <?= $statusColor ?>; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <div style="font-weight: bold; font-size: 1.1em; margin-bottom: 5px;">
                                        <?= $statusIcon ?>
                                        <a href="?page=invoices&action=view&id=<?= $invoice['id'] ?>" style="color: #0066cc;">
                                            <?= htmlspecialchars($invoice['invoice_number']) ?>
                                        </a>
                                        <?php if ($isForecast): ?>
                                            <span class="badge badge-forecast" style="margin-left: 10px;">Year <?= $invoice['forecast_year'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="color: #666; font-size: 0.9em;">
                                        <strong>Date:</strong> <?= date('d M Y', strtotime($invoice['invoice_date'])) ?>
                                        &nbsp;|&nbsp;
                                        <strong>Amount:</strong> £<?= number_format($invoice['invoice_amount'], 2) ?>
                                        &nbsp;|&nbsp;
                                        <strong>Cameras:</strong> <?= $invoice['camera_count'] ?>
                                        &nbsp;|&nbsp;
                                        <strong>Status:</strong> <?= $statusLabel ?>
                                    </div>
                                    <?php if ($isForecast): ?>
                                        <div style="margin-top: 8px; padding: 8px; background: #e3f2fd; border-radius: 4px; font-size: 0.85em; color: #1976d2;">
                                            💡 <strong>Forecast:</strong> This invoice will be auto-generated on <?= date('d M Y', strtotime($invoice['invoice_date'])) ?>
                                            <?php
                                            $inflationRate = (($invoice['invoice_amount'] / $parent['invoice_amount']) - 1) * 100;
                                            if ($inflationRate > 0):
                                            ?>
                                                (includes <?= number_format($inflationRate, 1) ?>% inflation)
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right;">
                                    <a href="?page=invoices&action=view&id=<?= $invoice['id'] ?>" class="btn btn-sm">View</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

