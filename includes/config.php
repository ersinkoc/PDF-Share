<?php
/**
 * Configuration Settings
 * 
 * Core configuration settings for the application
 */

// Prevent direct access
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

// Debug mode (set to false in production)
// if localhost or 127.0.0.1, set to true
if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] == 'pdflink.ddev.site' || $_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1')) {
    define('DEBUG_MODE', true);
} else {
    define('DEBUG_MODE', false);
}

// Define cache key for CSS
if (DEBUG_MODE) {
    define('CSS_CACHE_KEY', time());
} else {
    define('CSS_CACHE_KEY', '00001');
}

/**
 * Get base URL
 * 
 * @return string Base URL
 */
function getBaseUrl() {
    // Check if base URL is stored in settings
    static $baseUrl = null;
    
    if ($baseUrl !== null) {
        return $baseUrl;
    }
    
    // Try to get from database
    try {
        if (function_exists('getDbConnection')) {
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'general.base_url'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['setting_value'])) {
                $baseUrl = $result['setting_value'];
                return $baseUrl;
            }
        }
    } catch (Exception $e) {
        // Ignore database errors and continue with auto-detection
    }
    
    // For DDEV environment
    if (getenv('IS_DDEV_PROJECT') == 'true') {
        $baseUrl = 'https://pdflink.ddev.site/';
        return $baseUrl;
    }
    
    // Check if running from CLI
    if (php_sapi_name() === 'cli') {
        $baseUrl = 'http://localhost/';
        return $baseUrl;
    }
    
    // Auto-detect base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $path = preg_replace('#/[^/]*$#', '', $script);
    $path = preg_replace('#/admin$#', '', $path);
    $baseUrl = $protocol . $host . $path . '/';
    
    return $baseUrl;
}

// Base URL (with trailing slash)
define('BASE_URL', getBaseUrl());

// Database paths
$dbDir = BASE_PATH . 'database' . DIRECTORY_SEPARATOR;

// Create database directory if it doesn't exist
if (!file_exists($dbDir)) {
    mkdir($dbDir, 0755, true);
}

// Check if any .sqlite file exists in the database directory
$sqliteFiles = glob($dbDir . '*.sqlite');
if (!empty($sqliteFiles)) {
    // Use the first .sqlite file found
    define('DB_PATH', $sqliteFiles[0]);
} else {
    // Generate a random database name
    $randomDbName = 'db_' . bin2hex(random_bytes(8)) . '.sqlite';
    define('DB_PATH', $dbDir . $randomDbName);
}

define('AUDIT_DB_PATH', BASE_PATH . 'database/audit.sqlite');
define('DATABASE_FILE', DB_PATH); // For compatibility with getSystemStatus

// Directories
define('CACHE_DIR', BASE_PATH . 'cache' . DIRECTORY_SEPARATOR);
define('LOGS_DIR', BASE_PATH . 'logs' . DIRECTORY_SEPARATOR);
define('BACKUPS_DIR', BASE_PATH . 'backups' . DIRECTORY_SEPARATOR);
define('ERROR_LOG', LOGS_DIR . 'error.log');

// Create required directories if they don't exist
foreach ([CACHE_DIR, LOGS_DIR, BACKUPS_DIR] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Session settings
define('SESSION_TIMEOUT', 3600); // 1 hour

// CSRF token settings
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG);

// Time zone
date_default_timezone_set('UTC');

// Load settings from database if available
try {
    if (file_exists(DB_PATH)) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            define('SETTING_' . strtoupper($row['setting_key']), $row['setting_value']);
        }
    }
} catch (PDOException $e) {
    // Database not yet initialized or error connecting
    error_log('Database error: ' . $e->getMessage());
}

// Session settings - must be set before session_start()
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
    ini_set('session.use_strict_mode', 1);
}
