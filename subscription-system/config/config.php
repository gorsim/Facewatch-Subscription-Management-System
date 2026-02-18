<?php
/**
 * Facewatch Subscription Management System
 * Configuration File
 */

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Europe/London');

// Application settings
define('APP_NAME', 'Facewatch Subscription Management');
define('APP_VERSION', '1.0.0');
define('APP_ENV', getenv('APP_ENV') ?: 'development');

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'facewatch_subscriptions');
define('DB_USER', getenv('DB_USER') ?: 'facewatch_user');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Session configuration
define('SESSION_LIFETIME', 43200); // 12 hours
define('SESSION_NAME', 'facewatch_session');

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('BCRYPT_COST', 10);

// File upload
define('UPLOAD_MAX_SIZE', 16 * 1024 * 1024); // 16MB
define('UPLOAD_ALLOWED_TYPES', ['csv', 'xlsx', 'xls']);

// Pagination
define('ITEMS_PER_PAGE', 50);

// Date formats
define('DATE_FORMAT_DISPLAY', 'd/m/Y');
define('DATE_FORMAT_DB', 'Y-m-d');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('UPLOAD_DIR', ROOT_PATH . '/uploads/');

// Create directories
foreach ([UPLOAD_DIR, LOGS_PATH] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// Autoloader
spl_autoload_register(function ($class) {
    $file = APP_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

// Helper functions
require_once __DIR__ . '/helpers.php';

