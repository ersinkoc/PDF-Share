<?php
/**
 * Error Logs
 * 
 * Displays system error logs and allows downloading or clearing them
 */

// Include required files
require_once '../includes/init.php';
require_once '../includes/utilities.php'; // Include utilities.php

// Check if user is logged in and is admin
checkAdminAuth();

// Handle log clearing
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Invalid CSRF token. Please try again.';
    } else {
        $logType = $_POST['log_type'] ?? 'error';
        
        try {
            $logFile = '';
            switch ($logType) {
                case 'error':
                    $logFile = __DIR__ . '/../logs/error.log';
                    break;
                case 'access':
                    $logFile = __DIR__ . '/../logs/access.log';
                    break;
                default:
                    throw new Exception('Invalid log type specified.');
            }
            
            // Clear the log file by writing an empty string to it
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
                $successMessage = ucfirst($logType) . ' log cleared successfully.';
                
                // Log the action
                logActivity('clear_log_file', 'system', 0, [
                    'log_type' => $logType
                ]);
            } else {
                $errorMessage = 'Log file does not exist.';
            }
        } catch (Exception $e) {
            $errorMessage = 'Error clearing log: ' . $e->getMessage();
            logError($errorMessage);
        }
    }
}

// Get log type (default to 'error')
$logType = 'error';

// Get log file path
$logFile = __DIR__ . '/../logs/error.log';

// Get log content
$logContent = '';
$logSize = 0;
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $logSize = filesize($logFile);
    
    // Limit log content to last 1000 lines if it's too large
    if (substr_count($logContent, "\n") > 1000) {
        $lines = explode("\n", $logContent);
        $logContent = implode("\n", array_slice($lines, -1000));
    }
}

// Format log size
$logSizeFormatted = formatFileSize($logSize); // Using function from utilities.php

// Include header
$pageTitle = 'Error Logs';
include_once 'header.php';

?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><?php echo $pageTitle; ?></h1>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Log Actions</h2>
            <span class="text-sm text-gray-600">File Size: <?php echo $logSizeFormatted; ?></span>
        </div>
        
        <div class="flex flex-wrap gap-4">
            <form action="" method="post" class="inline" onsubmit="return confirm('Are you sure you want to clear the error log?');">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="log_type" value="error">
                <button type="submit" name="clear_logs" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
                    <i class="bi bi-trash mr-2"></i> Clear Log
                </button>
            </form>
        </div>
        
        <?php if (!empty($successMessage)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mt-4">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 bg-gray-50 border-b">
            <h3 class="font-semibold">Log Content</h3>
        </div>
        
        <?php if (empty($logContent)): ?>
            <div class="p-6 text-center text-gray-500">
                No log entries found.
            </div>
        <?php else: ?>
            <div class="p-4 overflow-x-auto">
                <pre class="text-xs font-mono bg-gray-100 p-4 rounded max-h-96 overflow-y-auto"><?php echo htmlspecialchars($logContent); ?></pre>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'footer.php'; ?>
