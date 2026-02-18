<?php
/**
 * Authentication and Authorization Functions
 */

/**
 * Start session if not already started
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    init_session();
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Require login (redirect to login page if not logged in)
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Check if user has required role
 */
function has_role($required_role) {
    if (!is_logged_in()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'];
    
    // Admin has access to everything
    if ($user_role === 'admin') {
        return true;
    }
    
    // Check specific role
    if ($user_role === $required_role) {
        return true;
    }
    
    // Manager has access to viewer functions
    if ($required_role === 'viewer' && $user_role === 'manager') {
        return true;
    }
    
    return false;
}

/**
 * Require specific role (redirect if not authorized)
 */
function require_role($required_role) {
    require_login();
    
    if (!has_role($required_role)) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied. You do not have permission to access this page.');
    }
}

/**
 * Login user
 */
function login_user($username, $password) {
    $sql = "SELECT id, username, email, password_hash, full_name, role, is_active 
            FROM users 
            WHERE username = ? OR email = ?";
    
    $user = db_query_one($sql, [$username, $username]);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Account is disabled'];
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    // Login successful - create session
    init_session();
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
    
    // Update last login
    db_execute("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
    
    // Log activity
    log_activity($user['id'], 'login', 'user', $user['id'], 'User logged in');
    
    // Create session record
    create_session_record($user['id']);
    
    return ['success' => true, 'user' => $user];
}

/**
 * Logout user
 */
function logout_user() {
    init_session();
    
    if (isset($_SESSION['user_id'])) {
        log_activity($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');
        delete_session_record($_SESSION['session_token'] ?? null);
    }
    
    session_destroy();
    setcookie(SESSION_NAME, '', time() - 3600, '/');
}

/**
 * Get current user
 */
function get_current_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['user_role']
    ];
}

/**
 * Create session record in database
 */
function create_session_record($user_id) {
    $session_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $sql = "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)";
    
    db_execute($sql, [$user_id, $session_token, $ip_address, $user_agent, $expires_at]);
    
    $_SESSION['session_token'] = $session_token;
}

/**
 * Delete session record from database
 */
function delete_session_record($session_token) {
    if ($session_token) {
        db_execute("DELETE FROM user_sessions WHERE session_token = ?", [$session_token]);
    }
}

/**
 * Clean up expired sessions
 */
function cleanup_expired_sessions() {
    db_execute("DELETE FROM user_sessions WHERE expires_at < NOW()");
}

