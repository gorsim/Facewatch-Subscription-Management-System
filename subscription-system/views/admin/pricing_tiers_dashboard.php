<?php
/**
 * Pricing Tiers Dashboard
 * Shows which pricing tier each legal entity is currently in based on camera count
 */

use App\Database;
use App\Services\PricingService;

$pageTitle = 'Pricing Tiers Dashboard';
$page = 'admin';

$db = Database::getInstance();
$pricingService = new PricingService();

// Get all legal entities with their camera counts
$entities = $db->fetchAll("
    SELECT 
        le.id,
        le.legal_entity_name,
        le.pricing_type,
        le.payment_frequency,
        COUNT(DISTINCT ci.id) as camera_count
    FROM legal_entities le
    LEFT JOIN stores s ON le.id = s.legal_entity_id
    LEFT JOIN camera_installations ci ON s.id = ci.store_id AND ci.removal_date IS NULL
    GROUP BY le.id, le.legal_entity_name, le.pricing_type, le.payment_frequency
    ORDER BY le.legal_entity_name
");

// Get pricing tier for each entity
$entityPricing = [];
foreach ($entities as $entity) {
    try {
        $pricing = $pricingService->getPricingForEntity(
            $entity['id'],
            (int) $entity['camera_count'],
            date('Y-m-d')
        );
        
        $entityPricing[] = array_merge($entity, [
            'tier_info' => $pricing,
            'annual_cost' => $pricing['price_per_annum'] * (int) $entity['camera_count'],
            'quarterly_cost' => $pricing['price_per_quarter'] * (int) $entity['camera_count'],
            'monthly_cost' => $pricing['price_per_month'] * (int) $entity['camera_count']
        ]);
    } catch (Exception $e) {
        $entityPricing[] = array_merge($entity, [
            'tier_info' => null,
            'error' => $e->getMessage()
        ]);
    }
}

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>📊 Pricing Tiers Dashboard</h1>
        <p style="color: #666;">
            Shows which pricing tier each legal entity is currently in based on their camera count.
            <a href="?page=admin&action=pricing">← Back to Pricing Management</a>
        </p>
    </div>

    <div class="card">
        <h2>Current Pricing Tiers by Legal Entity</h2>
        <p style="color: #666; margin-bottom: 20px;">
            As of <strong><?= date('d M Y') ?></strong>
        </p>

        <table>
            <thead>
                <tr>
                    <th>Legal Entity</th>
                    <th>Cameras</th>
                    <th>Pricing Type</th>
                    <th>Current Tier</th>
                    <th>Rate (P/A)</th>
                    <th>Rate (P/Q)</th>
                    <th>Rate (P/M)</th>
                    <th>Payment Freq</th>
                    <th>Total Cost</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entityPricing as $ep): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($ep['legal_entity_name']) ?></strong>
                        </td>
                        <td style="text-align: center;">
                            <strong><?= $ep['camera_count'] ?></strong>
                        </td>
                        <td>
                            <?php if ($ep['pricing_type'] === 'custom'): ?>
                                <span style="background: #fff3cd; padding: 3px 8px; border-radius: 3px;">
                                    🎯 Custom
                                </span>
                            <?php else: ?>
                                <span style="background: #d1ecf1; padding: 3px 8px; border-radius: 3px;">
                                    📊 Default
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($ep['tier_info'])): ?>
                                <strong><?= htmlspecialchars($ep['tier_info']['tier_name']) ?></strong>
                            <?php else: ?>
                                <span style="color: #dc3545;">Error</span>
                            <?php endif; ?>
                        </td>
                        <td>£<?= isset($ep['tier_info']) ? number_format($ep['tier_info']['price_per_annum'], 2) : '0.00' ?></td>
                        <td>£<?= isset($ep['tier_info']) ? number_format($ep['tier_info']['price_per_quarter'], 2) : '0.00' ?></td>
                        <td>£<?= isset($ep['tier_info']) ? number_format($ep['tier_info']['price_per_month'], 2) : '0.00' ?></td>
                        <td style="text-align: center;">
                            <strong><?= ucfirst($ep['payment_frequency'] ?? 'annual') ?></strong>
                        </td>
                        <td>
                            <?php if (isset($ep['tier_info'])): ?>
                                <?php
                                $freq = $ep['payment_frequency'] ?? 'annual';
                                $cost = $freq === 'monthly' ? $ep['monthly_cost'] : 
                                       ($freq === 'quarterly' ? $ep['quarterly_cost'] : $ep['annual_cost']);
                                $period = $freq === 'monthly' ? '/mo' : ($freq === 'quarterly' ? '/qtr' : '/yr');
                                ?>
                                <strong style="color: #28a745;">
                                    £<?= number_format($cost, 2) ?><?= $period ?>
                                </strong>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?page=subscribers&action=view&id=<?= $ep['id'] ?>" 
                               class="btn btn-sm">View</a>
                            <?php if ($ep['pricing_type'] === 'custom'): ?>
                                <a href="?page=admin&action=entity_pricing&id=<?= $ep['id'] ?>" 
                                   class="btn btn-sm">Pricing</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary Statistics -->
    <div class="card" style="margin-top: 20px;">
        <h2>Summary</h2>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
            <div>
                <h3 style="margin: 0; color: #666;">Total Entities</h3>
                <p style="font-size: 32px; margin: 10px 0; font-weight: bold;">
                    <?= count($entityPricing) ?>
                </p>
            </div>
            <div>
                <h3 style="margin: 0; color: #666;">Total Cameras</h3>
                <p style="font-size: 32px; margin: 10px 0; font-weight: bold;">
                    <?= array_sum(array_column($entityPricing, 'camera_count')) ?>
                </p>
            </div>
            <div>
                <h3 style="margin: 0; color: #666;">Custom Pricing</h3>
                <p style="font-size: 32px; margin: 10px 0; font-weight: bold;">
                    <?= count(array_filter($entityPricing, fn($e) => $e['pricing_type'] === 'custom')) ?>
                </p>
            </div>
            <div>
                <h3 style="margin: 0; color: #666;">Default Pricing</h3>
                <p style="font-size: 32px; margin: 10px 0; font-weight: bold;">
                    <?= count(array_filter($entityPricing, fn($e) => $e['pricing_type'] === 'default')) ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

