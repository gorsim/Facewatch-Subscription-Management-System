<?php
/**
 * Database Connection and Helper Functions
 */

// Global database connection
$db = null;

/**
 * Get database connection (singleton pattern)
 */
function get_db() {
    global $db;
    
    if ($db !== null) {
        return $db;
    }
    
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $db = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $db;
        
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        die('Database connection failed. Please check your configuration.');
    }
}

/**
 * Execute a query and return all results
 */
function db_query($sql, $params = []) {
    $db = get_db();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute a query and return single row
 */
function db_query_one($sql, $params = []) {
    $db = get_db();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Execute an INSERT/UPDATE/DELETE query
 */
function db_execute($sql, $params = []) {
    $db = get_db();
    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Get last insert ID
 */
function db_last_insert_id() {
    $db = get_db();
    return $db->lastInsertId();
}

/**
 * Begin transaction
 */
function db_begin_transaction() {
    $db = get_db();
    return $db->beginTransaction();
}

/**
 * Commit transaction
 */
function db_commit() {
    $db = get_db();
    return $db->commit();
}

/**
 * Rollback transaction
 */
function db_rollback() {
    $db = get_db();
    return $db->rollBack();
}

/**
 * Log audit entry
 */
function log_audit($user_id, $table_name, $record_id, $action, $old_values = null, $new_values = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $sql = "INSERT INTO audit_log (user_id, table_name, record_id, action, old_values, new_values, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        $user_id,
        $table_name,
        $record_id,
        $action,
        $old_values ? json_encode($old_values) : null,
        $new_values ? json_encode($new_values) : null,
        $ip_address
    ];
    
    return db_execute($sql, $params);
}

/**
 * Log user activity
 */
function log_activity($user_id, $action_type, $entity_type = null, $entity_id = null, $description = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $sql = "INSERT INTO user_activity_log (user_id, action_type, entity_type, entity_id, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $params = [
        $user_id,
        $action_type,
        $entity_type,
        $entity_id,
        $description,
        $ip_address
    ];
    
    return db_execute($sql, $params);
}

/**
 * Get pricing tier for a given camera count and date
 */
function get_pricing_tier($camera_count, $effective_date = null) {
    if ($effective_date === null) {
        $effective_date = date('Y-m-d');
    }
    
    $sql = "SELECT * FROM pricing_tiers 
            WHERE effective_date <= ? 
            AND min_cameras <= ? 
            AND (max_cameras IS NULL OR max_cameras >= ?)
            ORDER BY effective_date DESC, min_cameras DESC
            LIMIT 1";
    
    return db_query_one($sql, [$effective_date, $camera_count, $camera_count]);
}

/**
 * Get inflation rate for a given date
 */
function get_inflation_rate($effective_date) {
    $sql = "SELECT inflation_percentage FROM inflation_rates 
            WHERE effective_date <= ? 
            ORDER BY effective_date DESC 
            LIMIT 1";
    
    $result = db_query_one($sql, [$effective_date]);
    return $result ? $result['inflation_percentage'] : 0;
}

