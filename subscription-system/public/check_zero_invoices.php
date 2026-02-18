<?php
/**
 * Check why INV-001, INV-002, INV-003 have zero amounts
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Zero-Amount Invoice Check</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .section { margin: 30px 0; }
        h2 { color: #333; }
        .recommendation { background: #ffffcc; padding: 15px; border-left: 4px solid #ffcc00; }
    </style>
</head>
<body>
    <h1>Zero-Amount Invoice Check</h1>

    <div class="section">
        <h2>Invoice Details</h2>
        <?php
        $invoices = $db->fetchAll("
            SELECT 
                i.id,
                i.invoice_number,
                i.invoice_amount,
                i.invoice_status,
                i.legal_entity_id,
                i.invoice_date,
                le.entity_name,
                COUNT(ica.id) as camera_count,
                SUM(ica.price_charged) as total_from_allocations
            FROM invoices i
            LEFT JOIN legal_entities le ON i.legal_entity_id = le.id
            LEFT JOIN invoice_camera_allocations ica ON i.id = ica.invoice_id
            WHERE i.invoice_number IN ('INV-001', 'INV-002', 'INV-003', 'INV-006')
            GROUP BY i.id
            ORDER BY i.id
        ");
        ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Invoice #</th>
                <th>Entity</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Cameras Allocated</th>
                <th>Allocation Total</th>
            </tr>
            <?php foreach ($invoices as $inv): ?>
            <tr style="<?= $inv['invoice_amount'] == 0 ? 'background-color: #ffeeee;' : '' ?>">
                <td><?= $inv['id'] ?></td>
                <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
                <td><?= htmlspecialchars($inv['entity_name']) ?></td>
                <td>£<?= number_format($inv['invoice_amount'], 2) ?></td>
                <td><?= htmlspecialchars($inv['invoice_status']) ?></td>
                <td><?= $inv['camera_count'] ?></td>
                <td>£<?= number_format($inv['total_from_allocations'] ?? 0, 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>Available Cameras for Zero-Amount Invoices</h2>
        <?php foreach ($invoices as $inv): ?>
            <?php if ($inv['invoice_amount'] == 0): ?>
                <h3>Invoice <?= htmlspecialchars($inv['invoice_number']) ?> (ID: <?= $inv['id'] ?>)</h3>
                <p>
                    <strong>Entity:</strong> <?= htmlspecialchars($inv['entity_name']) ?><br>
                    <strong>Date:</strong> <?= $inv['invoice_date'] ?>
                </p>
                <?php
                $cameras = $db->fetchAll("
                    SELECT ci.id, ci.camera_name, s.store_name
                    FROM camera_installations ci
                    JOIN stores s ON ci.store_id = s.id
                    WHERE s.legal_entity_id = :legal_entity_id
                    AND ci.removal_date IS NULL
                ", ['legal_entity_id' => $inv['legal_entity_id']]);
                ?>
                <p><strong>Available cameras:</strong> <?= count($cameras) ?></p>
                <?php if (count($cameras) > 0): ?>
                    <p><strong>Camera IDs:</strong> <?= implode(', ', array_column($cameras, 'id')) ?></p>
                    <table>
                        <tr>
                            <th>Camera ID</th>
                            <th>Camera Name</th>
                            <th>Store</th>
                        </tr>
                        <?php foreach ($cameras as $cam): ?>
                        <tr>
                            <td><?= $cam['id'] ?></td>
                            <td><?= htmlspecialchars($cam['camera_name']) ?></td>
                            <td><?= htmlspecialchars($cam['store_name']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="recommendation">
        <h2>Recommendation</h2>
        <p>Invoices with £0.00 amounts need to have cameras allocated to them.</p>
        <p>This can be done by:</p>
        <ol>
            <li>Using the invoice edit page to allocate cameras</li>
            <li>Running a script to allocate cameras programmatically</li>
            <li>Deleting these invoices and recreating them with proper camera allocation</li>
        </ol>
    </div>
</body>
</html>

