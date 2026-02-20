<?php
/**
 * Store Camera History
 * Shows complete timeline of all cameras at a store
 */

use App\Database;

$pageTitle = 'Camera History';
$page = 'subscribers';

$db = Database::getInstance();

// Get store ID from URL
$storeId = $_GET['store_id'] ?? null;

if (!$storeId) {
    header('Location: ?page=subscribers');
    exit;
}

// Get store details
$store = $db->fetchOne("
    SELECT s.*, le.legal_entity_name, le.legal_entity_id
    FROM stores s
    JOIN legal_entities le ON s.legal_entity_id = le.id
    WHERE s.id = :id
", ['id' => $storeId]);

if (!$store) {
    header('Location: ?page=subscribers');
    exit;
}

// Get complete camera history for this store
// This includes:
// 1. All camera installations (current and removed)
// 2. Camera movements TO this store
// 3. Camera movements FROM this store
$cameraHistory = $db->fetchAll("
    -- Original installations (show as separate event)
    SELECT
        ci.id,
        CAST(ci.safr_code AS CHAR) COLLATE utf8mb4_unicode_ci as safr_code,
        CAST(ci.camera_name AS CHAR) COLLATE utf8mb4_unicode_ci as camera_name,
        ci.camera_type,
        ci.installation_date,
        NULL as removal_date,
        CAST(ci.notes AS CHAR) COLLATE utf8mb4_unicode_ci as notes,
        'installed' as event_type,
        NULL as movement_id,
        CAST(NULL AS CHAR) COLLATE utf8mb4_unicode_ci as from_store_name,
        CAST(NULL AS CHAR) COLLATE utf8mb4_unicode_ci as to_store_name
    FROM camera_installations ci
    WHERE ci.store_id = :store_id1

    UNION ALL

    -- Removals (show as separate event)
    SELECT
        ci.id,
        CAST(ci.safr_code AS CHAR) COLLATE utf8mb4_unicode_ci as safr_code,
        CAST(ci.camera_name AS CHAR) COLLATE utf8mb4_unicode_ci as camera_name,
        ci.camera_type,
        ci.removal_date as installation_date,
        ci.removal_date,
        CAST(CONCAT('Removed from store', CASE WHEN ci.notes IS NOT NULL THEN CONCAT(' - ', ci.notes) ELSE '' END) AS CHAR) COLLATE utf8mb4_unicode_ci as notes,
        'removed' as event_type,
        NULL as movement_id,
        CAST(NULL AS CHAR) COLLATE utf8mb4_unicode_ci as from_store_name,
        CAST(NULL AS CHAR) COLLATE utf8mb4_unicode_ci as to_store_name
    FROM camera_installations ci
    WHERE ci.store_id = :store_id2
    AND ci.removal_date IS NOT NULL

    UNION ALL

    -- Camera movements IN
    SELECT
        ci.id,
        CAST(cm.safr_code AS CHAR) COLLATE utf8mb4_unicode_ci as safr_code,
        CAST(cm.camera_name AS CHAR) COLLATE utf8mb4_unicode_ci as camera_name,
        ci.camera_type,
        cm.installation_date,
        NULL as removal_date,
        CAST(CONCAT('Moved from: ', cm.from_store_name) AS CHAR) COLLATE utf8mb4_unicode_ci as notes,
        'moved_in' as event_type,
        cm.id as movement_id,
        CAST(cm.from_store_name AS CHAR) COLLATE utf8mb4_unicode_ci as from_store_name,
        CAST(cm.to_store_name AS CHAR) COLLATE utf8mb4_unicode_ci as to_store_name
    FROM camera_movements cm
    JOIN camera_installations ci ON cm.camera_installation_id = ci.id
    WHERE cm.to_store_id = :store_id3

    UNION ALL

    -- Camera movements OUT
    SELECT
        ci.id,
        CAST(cm.safr_code AS CHAR) COLLATE utf8mb4_unicode_ci as safr_code,
        CAST(cm.camera_name AS CHAR) COLLATE utf8mb4_unicode_ci as camera_name,
        ci.camera_type,
        cm.removal_date as installation_date,
        cm.removal_date,
        CAST(CONCAT('Moved to: ', cm.to_store_name) AS CHAR) COLLATE utf8mb4_unicode_ci as notes,
        'moved_out' as event_type,
        cm.id as movement_id,
        CAST(cm.from_store_name AS CHAR) COLLATE utf8mb4_unicode_ci as from_store_name,
        CAST(cm.to_store_name AS CHAR) COLLATE utf8mb4_unicode_ci as to_store_name
    FROM camera_movements cm
    JOIN camera_installations ci ON cm.camera_installation_id = ci.id
    WHERE cm.from_store_id = :store_id4

    ORDER BY safr_code, installation_date ASC
", [
    'store_id1' => $storeId,
    'store_id2' => $storeId,
    'store_id3' => $storeId,
    'store_id4' => $storeId
]);

// Group by SAFR code to show complete timeline for each camera
$cameraTimelines = [];
foreach ($cameraHistory as $event) {
    $safrCode = $event['safr_code'] ?: 'Unknown';
    if (!isset($cameraTimelines[$safrCode])) {
        $cameraTimelines[$safrCode] = [];
    }
    $cameraTimelines[$safrCode][] = $event;
}

// DEBUG: Show what we got from database
echo "<!-- DEBUG: Raw data from database -->\n";
foreach ($cameraTimelines as $safrCode => $events) {
    echo "<!-- Camera: $safrCode -->\n";
    foreach ($events as $event) {
        echo "<!--   {$event['event_type']}: {$event['installation_date']} -->\n";
    }
}

// First, sort events within each camera timeline by date (newest first)
foreach ($cameraTimelines as $safrCode => &$events) {
    usort($events, function($a, $b) {
        // Compare by installation_date (which holds the event date)
        // Return negative if $b should come before $a (descending order)
        $dateA = $a['installation_date'];
        $dateB = $b['installation_date'];

        // Descending order: newer dates first
        if ($dateB > $dateA) return 1;
        if ($dateB < $dateA) return -1;
        return 0;
    });
}
unset($events); // Break reference

// DEBUG: Show what we have after sorting
echo "<!-- DEBUG: After sorting -->\n";
foreach ($cameraTimelines as $safrCode => $events) {
    echo "<!-- Camera: $safrCode -->\n";
    foreach ($events as $event) {
        echo "<!--   {$event['event_type']}: {$event['installation_date']} -->\n";
    }
}

// Sort camera boxes: active cameras first, then inactive by original install date
uasort($cameraTimelines, function($a, $b) {
    // Check if cameras are active (latest event is 'installed' or 'moved_in')
    // After reversing, $a[0] is the newest event
    $aActive = ($a[0]['event_type'] === 'installed' || $a[0]['event_type'] === 'moved_in');
    $bActive = ($b[0]['event_type'] === 'installed' || $b[0]['event_type'] === 'moved_in');

    // Active cameras come first
    if ($aActive && !$bActive) return -1;
    if (!$aActive && $bActive) return 1;

    // If both inactive, sort by original installation date (oldest first)
    // After reversing, the last event in the array is the oldest
    if (!$aActive && !$bActive) {
        $aOldest = end($a)['installation_date'];
        $bOldest = end($b)['installation_date'];
        return strcmp($aOldest, $bOldest);
    }

    // If both active, sort by SAFR code
    return strcmp($a[0]['safr_code'], $b[0]['safr_code']);
});

// Get current active cameras count
$activeCameras = $db->fetchOne("
    SELECT COUNT(*) as count
    FROM camera_installations
    WHERE store_id = :store_id
    AND removal_date IS NULL
", ['store_id' => $storeId])['count'] ?? 0;

// Get total cameras ever installed
$totalCameras = count($cameraTimelines);

require __DIR__ . '/../layouts/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2>📹 Camera History</h2>
            <p style="margin: 5px 0 0 0; color: #666;">
                <strong><?= htmlspecialchars($store['store_name']) ?></strong>
                (<?= htmlspecialchars($store['legal_entity_name']) ?>)
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="?page=subscribers&action=view&id=<?= $store['legal_entity_id'] ?>" class="btn">
                ← Back to Legal Entity
            </a>
        </div>
    </div>

    <!-- Summary Stats -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px;">
        <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold; color: #2e7d32;"><?= $activeCameras ?></div>
            <div style="color: #666; margin-top: 5px;">Currently Active</div>
        </div>
        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold; color: #1976d2;"><?= $totalCameras ?></div>
            <div style="color: #666; margin-top: 5px;">Total Cameras (All Time)</div>
        </div>
        <div style="background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold; color: #e65100;"><?= $totalCameras - $activeCameras ?></div>
            <div style="color: #666; margin-top: 5px;">Removed/Moved</div>
        </div>
    </div>

    <!-- Camera Timelines -->
    <h3 style="margin-bottom: 15px;">Complete Camera Timeline</h3>
    <p style="color: #666; margin-bottom: 20px;">
        This shows the complete history of every camera at this store, including when they were installed,
        removed, or moved to/from other stores.
    </p>

    <?php if (empty($cameraTimelines)): ?>
        <div style="padding: 40px; text-align: center; background: #f5f5f5; border-radius: 8px;">
            <p style="color: #999; font-size: 1.2em;">No camera history found for this store</p>
        </div>
    <?php else: ?>
        <?php foreach ($cameraTimelines as $safrCode => $events): ?>
            <?php
            // Determine current status - camera is active if latest event is 'installed' or 'moved_in'
            $isActive = false;
            $latestEvent = $events[0]; // Events are reversed, so [0] is the newest event
            if ($latestEvent['event_type'] === 'installed' || $latestEvent['event_type'] === 'moved_in') {
                $isActive = true;
            }
            ?>
            <div style="margin-bottom: 30px; border: 2px solid <?= $isActive ? '#4caf50' : '#ddd' ?>; border-radius: 8px; overflow: hidden;">
                <!-- Camera Header -->
                <div style="background: <?= $isActive ? 'linear-gradient(135deg, #4caf50 0%, #66bb6a 100%)' : '#f5f5f5' ?>;
                            color: <?= $isActive ? 'white' : '#333' ?>;
                            padding: 15px 20px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;">
                    <div>
                        <h4 style="margin: 0; font-size: 1.2em;">
                            📹 <?= htmlspecialchars($safrCode) ?>
                            <?php if ($latestEvent['camera_name']): ?>
                                - <?= htmlspecialchars($latestEvent['camera_name']) ?>
                            <?php endif; ?>
                        </h4>
                        <div style="margin-top: 5px; opacity: 0.9; font-size: 0.9em;">
                            <?= ucfirst($latestEvent['camera_type']) ?> Camera
                        </div>
                    </div>
                    <div>
                        <?php if ($isActive): ?>
                            <span style="background: rgba(255,255,255,0.3); padding: 8px 16px; border-radius: 20px; font-weight: bold;">
                                ✓ ACTIVE
                            </span>
                        <?php else: ?>
                            <span style="background: #e0e0e0; color: #666; padding: 8px 16px; border-radius: 20px; font-weight: bold;">
                                REMOVED
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Timeline Events -->
                <div style="padding: 20px;">
                    <div style="position: relative; padding-left: 40px;">
                        <!-- Timeline line -->
                        <div style="position: absolute; left: 15px; top: 0; bottom: 0; width: 2px; background: #ddd;"></div>

                        <?php foreach ($events as $index => $event): ?>
                            <div style="position: relative; margin-bottom: <?= $index < count($events) - 1 ? '25px' : '0' ?>;">
                                <!-- Timeline dot -->
                                <div style="position: absolute; left: -32px; top: 5px; width: 12px; height: 12px;
                                            background: <?= $event['event_type'] === 'installed' ? '#2196f3' :
                                                          ($event['event_type'] === 'removed' ? '#f44336' :
                                                          ($event['event_type'] === 'moved_in' ? '#4caf50' : '#ff9800')) ?>;
                                            border-radius: 50%; border: 3px solid white; box-shadow: 0 0 0 2px #ddd;"></div>

                                <!-- Event details -->
                                <div style="background: #f8f9fa; padding: 12px 15px; border-radius: 6px; border-left: 3px solid
                                            <?= $event['event_type'] === 'installed' ? '#2196f3' :
                                               ($event['event_type'] === 'removed' ? '#f44336' :
                                               ($event['event_type'] === 'moved_in' ? '#4caf50' : '#ff9800')) ?>;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <strong style="color: #333; font-size: 1.05em;">
                                                <?php if ($event['event_type'] === 'installed'): ?>
                                                    ✅ Installed
                                                <?php elseif ($event['event_type'] === 'removed'): ?>
                                                    🔴 Removed
                                                <?php elseif ($event['event_type'] === 'moved_in'): ?>
                                                    📥 Moved In
                                                <?php else: ?>
                                                    📤 Moved Out
                                                <?php endif; ?>
                                            </strong>
                                            <div style="color: #666; margin-top: 5px; font-size: 0.95em;">
                                                <?= date('d M Y', strtotime($event['installation_date'])) ?>
                                            </div>
                                            <?php if ($event['notes']): ?>
                                                <div style="margin-top: 8px; padding: 8px; background: white; border-radius: 4px; font-size: 0.9em;">
                                                    💬 <?= htmlspecialchars($event['notes']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($event['event_type'] === 'moved_in'): ?>
                                                <div style="margin-top: 8px; color: #4caf50; font-size: 0.9em;">
                                                    ← From: <strong><?= htmlspecialchars($event['from_store_name']) ?></strong>
                                                </div>
                                            <?php elseif ($event['event_type'] === 'moved_out'): ?>
                                                <div style="margin-top: 8px; color: #ff9800; font-size: 0.9em;">
                                                    → To: <strong><?= htmlspecialchars($event['to_store_name']) ?></strong>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

