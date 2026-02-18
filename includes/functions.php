<?php
/**
 * General Helper Functions
 */

/**
 * Sanitize output for HTML display
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format date for display
 */
function format_date($date, $format = DATE_FORMAT_DISPLAY) {
    if (empty($date)) {
        return '';
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format currency
 */
function format_currency($amount, $currency = '£') {
    return $currency . number_format($amount, 2);
}

/**
 * Format number
 */
function format_number($number, $decimals = 0) {
    return number_format($number, $decimals);
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set flash message
 */
function set_flash($type, $message) {
    init_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function get_flash() {
    init_session();
    
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    
    return null;
}

/**
 * Validate email
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random password
 */
function generate_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $max = strlen($chars) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}

/**
 * Hash password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

/**
 * Parse CSV file
 */
function parse_csv($filepath, $has_header = true) {
    if (!file_exists($filepath)) {
        return ['success' => false, 'message' => 'File not found'];
    }
    
    $rows = [];
    $header = [];
    
    if (($handle = fopen($filepath, 'r')) !== false) {
        $row_num = 0;
        
        while (($data = fgetcsv($handle)) !== false) {
            $row_num++;
            
            if ($has_header && $row_num === 1) {
                $header = $data;
                continue;
            }
            
            if ($has_header && !empty($header)) {
                $row = [];
                foreach ($data as $index => $value) {
                    $key = $header[$index] ?? "column_$index";
                    $row[$key] = $value;
                }
                $rows[] = $row;
            } else {
                $rows[] = $data;
            }
        }
        
        fclose($handle);
    }
    
    return ['success' => true, 'data' => $rows, 'header' => $header];
}

/**
 * Validate uploaded file
 */
function validate_upload($file) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error: ' . $file['error']];
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum size: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)];
    }
    
    return ['success' => true];
}

/**
 * Save uploaded file
 */
function save_upload($file, $prefix = '') {
    $validation = validate_upload($file);
    if (!$validation['success']) {
        return $validation;
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $prefix . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
    $filepath = UPLOAD_DIR . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Failed to save file'];
    }
    
    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

/**
 * Calculate days between two dates
 */
function days_between($date1, $date2) {
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    return abs($d1->diff($d2)->days);
}

/**
 * Get pagination data
 */
function get_pagination($total_items, $current_page = 1, $items_per_page = ITEMS_PER_PAGE) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'total_items' => $total_items,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'items_per_page' => $items_per_page,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

