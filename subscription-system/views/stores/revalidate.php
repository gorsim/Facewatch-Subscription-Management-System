<?php
use App\Services\CameraValidationService;

// Get store ID
$storeId = $_GET['id'] ?? null;

if (!$storeId) {
    $_SESSION['error'] = 'Store ID is required';
    header('Location: ?page=subscribers');
    exit;
}

// Re-validate all snapshots for this store
$validationService = new CameraValidationService();
$validationService->validateSnapshotsForStore($storeId);

$_SESSION['success'] = 'All snapshots have been re-validated successfully';
header('Location: ?page=stores&action=view&id=' . $storeId);
exit;

