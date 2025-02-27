<?php
/**
 * Download Log File
 * 
 * Allows administrators to download log files
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Validate CSRF token
if (!validateCSRFToken($_GET['csrf_token'] ?? '')) {
    die('Invalid CSRF token. Please try again.');
}

// Get log type
$logType = isset($_GET['type']) ? $_GET['type'] : 'error';

// Define log file path based on log type
$logFile = '';
switch ($logType) {
    case 'error':
        $logFile = __DIR__ . '/../logs/error.log';
        $fileName = 'error_log_' . date('Y-m-d') . '.log';
        break;
    case 'access':
        $logFile = __DIR__ . '/../logs/access.log';
        $fileName = 'access_log_' . date('Y-m-d') . '.log';
        break;
    default:
        die('Invalid log type specified.');
}

// Check if file exists
if (!file_exists($logFile)) {
    die('Log file does not exist.');
}

// Log the download activity
logActivity('download_log', 'system', 0, [
    'log_type' => $logType,
    'file_name' => $fileName
]);

// Set headers for file download
header('Content-Description: File Transfer');
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($logFile));

// Clear output buffer
ob_clean();
flush();

// Output file
readfile($logFile);
exit;
