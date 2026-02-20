<?php
/**
 * Main Entry Point
 * Facewatch Subscription Management System
 */

// Start session
session_start();

// Set timezone
date_default_timezone_set('Europe/London');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load config
$appConfig = require __DIR__ . '/../config/app.php';

// Simple routing
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

// DEBUG: Log routing information
error_log("index.php routing - Page: $page, Action: $action");

// Check if user is logged in (except for login page)
if ($page !== 'login' && !isset($_SESSION['user_id'])) {
    header('Location: ?page=login');
    exit;
}

// Route to appropriate controller
try {
    switch ($page) {
        case 'login':
            require __DIR__ . '/../views/auth/login.php';
            break;
            
        case 'dashboard':
            require __DIR__ . '/../views/dashboard/index.php';
            break;
            
        case 'subscribers':
            if ($action === 'view' || $action === 'edit' || $action === 'new' || $action === 'delete' || $action === 'save_pricing_type_session') {
                require __DIR__ . '/../views/customers/' . $action . '.php';
            } else {
                require __DIR__ . '/../views/customers/index.php';
            }
            break;

        case 'stores':
            if ($action === 'edit' || $action === 'new' || $action === 'delete' || $action === 'view' || $action === 'revalidate' || $action === 'camera_history') {
                require __DIR__ . '/../views/stores/' . $action . '.php';
            } else {
                // Redirect to subscribers page if no action
                header('Location: ?page=subscribers');
                exit;
            }
            break;

        case 'cameras':
            if ($action === 'edit' || $action === 'new' || $action === 'delete' || $action === 'movements' || $action === 'movement_detail') {
                require __DIR__ . '/../views/cameras/' . $action . '.php';
            } else {
                // Redirect to dashboard if no action
                header('Location: ?page=dashboard');
                exit;
            }
            break;

        case 'invoices':
            $validActions = ['create', 'delete', 'view', 'allocate', 'debug_allocations', 'cleanup_duplicates', 'reset_all', 'change_status', 'reconcile_to_xero', 'bulk_reconcile', 'bulk_status_update', 'test_view', 'reconcile_form', 'smart_match', 'match_action', 'manual_match', 'cluster', 'delete_xero_invoice', 'generator', 'create_from_generator', 'calculate_pricing', 'update_dates', 'regenerate_forecasts', 'recalculate_all', 'review_pricing', 'create_from_review'];

            error_log("index.php invoices case - Action: $action, Valid: " . (in_array($action, $validActions) ? 'YES' : 'NO'));
            if (in_array($action, $validActions)) {
                $filePath = __DIR__ . '/../views/invoices/' . $action . '.php';
                error_log("index.php loading file: $filePath");
                require $filePath;
            } else {
                error_log("index.php loading default invoices/index.php");
                require __DIR__ . '/../views/invoices/index.php';
            }
            break;

        case 'import':
            require __DIR__ . '/../views/imports/index.php';
            break;

        case 'imports':
            $action = $_GET['action'] ?? 'index';
            if ($action === 'history') {
                require __DIR__ . '/../views/imports/history.php';
            } elseif ($action === 'view_import') {
                require __DIR__ . '/../views/imports/view_import.php';
            } else {
                require __DIR__ . '/../views/imports/index.php';
            }
            break;

        case 'reports':
            require __DIR__ . '/../views/reports/index.php';
            break;

        case 'admin':
            if ($action === 'pricing') {
                require __DIR__ . '/../views/admin/pricing.php';
            } elseif ($action === 'import_pricing') {
                require __DIR__ . '/../views/admin/import_pricing.php';
            } elseif ($action === 'entity_pricing') {
                require __DIR__ . '/../views/admin/entity_pricing.php';
            } elseif ($action === 'independent_pricing') {
                require __DIR__ . '/../views/pricing/store_pricing.php';
            } elseif ($action === 'pricing_dashboard') {
                require __DIR__ . '/../views/admin/pricing_tiers_dashboard.php';
            } else {
                // Default admin page - redirect to pricing for now
                header('Location: ?page=admin&action=pricing');
                exit;
            }
            break;

        case 'settings':
            if ($action === 'account') {
                require __DIR__ . '/../views/settings/account.php';
            } elseif ($action === 'users') {
                require __DIR__ . '/../views/settings/users.php';
            } else {
                // Default to account settings
                header('Location: ?page=settings&action=account');
                exit;
            }
            break;

        case 'logout':
            session_destroy();
            header('Location: ?page=login');
            exit;
            
        default:
            require __DIR__ . '/../views/dashboard/index.php';
    }
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    if ($appConfig['debug'] ?? false) {
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
}

