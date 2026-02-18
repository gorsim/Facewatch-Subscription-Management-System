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

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'facewatch_subscriptions');
define('DB_USER', getenv('DB_USER') ?: 'facewatch_user');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'Facewatch Subscription Management');
define('APP_VERSION', '1.0.0');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8000');

// Session Settings
define('SESSION_LIFETIME', 43200); // 12 hours in seconds
define('SESSION_NAME', 'facewatch_session');

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_UPLOAD_SIZE', 16 * 1024 * 1024); // 16MB
define('ALLOWED_EXTENSIONS', ['csv', 'xlsx', 'xls']);

// Pagination
define('ITEMS_PER_PAGE', 50);

// Date Formats
define('DATE_FORMAT_DISPLAY', 'd/m/Y');
define('DATE_FORMAT_DB', 'Y-m-d');
define('DATETIME_FORMAT_DISPLAY', 'd/m/Y H:i');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('BCRYPT_COST', 10);

// Business Logic Constants
define('DAYS_PER_YEAR', 365);
define('DAYS_PER_QUARTER', 91.25);
define('DAYS_PER_MONTH', 30.42); // Average

// Ensure upload directory exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Auto-load helper functions
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
require_once INCLUDES_PATH . '/auth.php';

