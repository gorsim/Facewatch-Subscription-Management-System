<?php
/**
 * Helper Functions
 */

/**
 * Escape HTML output
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Format date for display
 */
function formatDate($date, $format = DATE_FORMAT_DISPLAY) {
    if (empty($date)) return '';
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Format currency
 */
function formatCurrency($amount, $symbol = '£') {
    return $symbol . number_format($amount, 2);
}

/**
 * Format number
 */
function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user
 */
function currentUser() {
    if (!isLoggedIn()) return null;
    return $_SESSION['user'] ?? null;
}

/**
 * Check if user has role
 */
function hasRole($role) {
    $user = currentUser();
    if (!$user) return false;
    
    if (is_array($role)) {
        return in_array($user['role'], $role);
    }
    return $user['role'] === $role;
}

/**
 * Require authentication
 */
function requireAuth() {
    if (!isLoggedIn()) {
        redirect('/login.php');
    }
}

/**
 * Require specific role
 */
function requireRole($role) {
    requireAuth();
    if (!hasRole($role)) {
        die('Access denied. Insufficient permissions.');
    }
}

/**
 * Flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Log message to file
 */
function logMessage($message, $level = 'INFO') {
    $logFile = LOGS_PATH . '/app_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Debug dump (only in development)
 */
function dd($var) {
    if (APP_ENV === 'development') {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
        die();
    }
}

/**
 * Calculate prepayment
 */
function calculatePrepayment($invoiceAmount, $invoiceDate, $calculationDate, $paymentFrequency) {
    $invoice = new DateTime($invoiceDate);
    $calc = new DateTime($calculationDate);
    $daysSince = $invoice->diff($calc)->days;
    
    // Determine period length
    $daysInPeriod = match($paymentFrequency) {
        'annual' => 365,
        'quarterly' => 91.25,
        'monthly' => 30,
        default => 365
    };
    
    // Calculate prepayment
    if ($daysSince >= $daysInPeriod) {
        return 0; // Fully recognized
    }
    
    $daysRemaining = $daysInPeriod - $daysSince;
    $prepayment = ($invoiceAmount / $daysInPeriod) * $daysRemaining;
    
    return round($prepayment, 2);
}

