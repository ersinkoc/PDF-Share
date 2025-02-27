<?php
/**
 * Application Initialization
 * 
 * This file initializes the application by including all required files
 * and setting up the environment.
 */

require_once __DIR__ . '/config.php';

function redirect($url) {
    header("Location: $url");
    exit;
}

// Define base path if not already defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    
    session_start();
}

// Enable error reporting in debug mode
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set error log
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG);

// Set timezone
date_default_timezone_set('UTC');

// Create logs directory if it doesn't exist
if (!file_exists(LOGS_DIR)) {
    mkdir(LOGS_DIR, 0755, true);
}

// Create operations logs directory if it doesn't exist
$operationsLogDir = LOGS_DIR . '/operations';
if (!file_exists($operationsLogDir)) {
    mkdir($operationsLogDir, 0755, true);
}

// Load utilities first to avoid function redeclaration issues
require_once __DIR__ . '/utilities.php';

// Initialize database connection
require_once __DIR__ . '/database.php';

// Load migrations system and run migrations
require_once __DIR__ . '/migrations.php';
runMigrations();

// Load error handler
require_once __DIR__ . '/error_handler.php';

/**
 * Check if user is authenticated as admin
 * 
 * @return bool True if user is admin, false otherwise
 */
function checkAdminAuth() {
    if (!isLoggedIn()) {
        // Redirect to login page
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    }
    
    // Check if user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        // Not an admin, redirect to home
        header('Location: ' . BASE_URL);
        exit;
    }
    
    return true;
}

define('MAX_STORAGE_SIZE', getSettingValue('storage.max_space')); 
define('MAX_FILE_SIZE', getSettingValue('upload.max_file_size')); 