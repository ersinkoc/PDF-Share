<?php
/**
 * Error Handler
 * 
 * Custom error handling functions
 */

// Include configuration
require_once __DIR__ . '/config.php';

/**
 * Custom error handler
 * 
 * @param int $errno Error level
 * @param string $errstr Error message
 * @param string $errfile File where error occurred
 * @param int $errline Line where error occurred
 * @return bool True to prevent default error handler
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // Get error type
    $errorType = 'Unknown Error';
    
    switch ($errno) {
        case E_ERROR:
            $errorType = 'Fatal Error';
            break;
        case E_WARNING:
            $errorType = 'Warning';
            break;
        case E_PARSE:
            $errorType = 'Parse Error';
            break;
        case E_NOTICE:
            $errorType = 'Notice';
            break;
        case E_CORE_ERROR:
            $errorType = 'Core Error';
            break;
        case E_CORE_WARNING:
            $errorType = 'Core Warning';
            break;
        case E_COMPILE_ERROR:
            $errorType = 'Compile Error';
            break;
        case E_COMPILE_WARNING:
            $errorType = 'Compile Warning';
            break;
        case E_USER_ERROR:
            $errorType = 'User Error';
            break;
        case E_USER_WARNING:
            $errorType = 'User Warning';
            break;
        case E_USER_NOTICE:
            $errorType = 'User Notice';
            break;
        case E_STRICT:
            $errorType = 'Strict Standards';
            break;
        case E_RECOVERABLE_ERROR:
            $errorType = 'Recoverable Error';
            break;
        case E_DEPRECATED:
            $errorType = 'Deprecated';
            break;
        case E_USER_DEPRECATED:
            $errorType = 'User Deprecated';
            break;
    }
    
    // Format error message
    $message = "[{$errorType}] {$errstr} in {$errfile} on line {$errline}";
    
    // Log error
    if (!file_exists(LOGS_DIR)) {
        mkdir(LOGS_DIR, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
    
    file_put_contents(ERROR_LOG, $logMessage, FILE_APPEND);
    
    // Display error message if in development mode
    if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px;'>";
        echo "<strong>{$errorType}:</strong> {$errstr}<br>";
        echo "File: {$errfile}<br>";
        echo "Line: {$errline}";
        echo "</div>";
    }
    
    // Don't execute PHP internal error handler
    return true;
}

/**
 * Custom exception handler
 * 
 * @param Throwable $exception The exception object
 */
function customExceptionHandler($exception) {
    // Format exception message
    $message = "Uncaught Exception: " . $exception->getMessage() . 
               " in " . $exception->getFile() . 
               " on line " . $exception->getLine() . 
               "\nStack trace: " . $exception->getTraceAsString();
    
    // Log exception
    if (!file_exists(LOGS_DIR)) {
        mkdir(LOGS_DIR, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
    
    file_put_contents(ERROR_LOG, $logMessage, FILE_APPEND);
    
    // Check if headers have already been sent
    if (!headers_sent()) {
        // Display error page
        http_response_code(500);
    }
    
    if (file_exists(BASE_PATH . 'error.php')) {
        include BASE_PATH . 'error.php';
    } else {
        echo "<h1>Server Error</h1>";
        echo "<p>An unexpected error occurred. Please try again later.</p>";
    }
    exit;
}

/**
 * Custom shutdown function to catch fatal errors
 */
function customShutdownFunction() {
    $error = error_get_last();
    
    if ($error !== null && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        // Format error message
        $message = "Fatal Error: " . $error['message'] . 
                   " in " . $error['file'] . 
                   " on line " . $error['line'];
        
        // Log error
        if (!file_exists(LOGS_DIR)) {
            mkdir(LOGS_DIR, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        file_put_contents(ERROR_LOG, $logMessage, FILE_APPEND);
        
        // Check if headers have already been sent
        if (!headers_sent()) {
            // Display error page
            http_response_code(500);
        }
        
        if (file_exists(BASE_PATH . 'error.php')) {
            include BASE_PATH . 'error.php';
        } else {
            echo "<h1>Server Error</h1>";
            echo "<p>An unexpected error occurred. Please try again later.</p>";
        }
    }
}

// Set custom error handlers
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');
register_shutdown_function('customShutdownFunction');
