<?php
/**
 * Export Logs
 * 
 * Exports audit logs or operation logs to CSV or JSON format
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Get parameters
$logType = isset($_GET['type']) && $_GET['type'] === 'operations' ? 'operations' : 'audit';
$format = isset($_GET['format']) && $_GET['format'] === 'json' ? 'json' : 'csv';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-d');
}

// Add time to end date to include the entire day
$endDate = $endDate . ' 23:59:59';

// Get logs
$logs = [];

if ($logType === 'audit') {
    // Get audit logs from database
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM audit_log WHERE created_at BETWEEN :start_date AND :end_date ORDER BY created_at DESC");
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process details column
    foreach ($logs as &$log) {
        if (isset($log['details']) && !empty($log['details'])) {
            $log['details_decoded'] = json_decode($log['details'], true);
        } else {
            $log['details_decoded'] = [];
        }
    }
} else {
    // Get operation logs from JSON files
    $logsDir = __DIR__ . '/../logs/operations';
    if (file_exists($logsDir)) {
        $logFiles = glob($logsDir . '/*.json');
        
        $allLogs = [];
        foreach ($logFiles as $file) {
            $fileDate = basename($file, '.json');
            
            // Skip files outside date range
            if ($fileDate < $startDate || $fileDate > substr($endDate, 0, 10)) {
                continue;
            }
            
            if (file_exists($file)) {
                $fileContent = file_get_contents($file);
                if (!empty($fileContent)) {
                    $fileLogs = json_decode($fileContent, true) ?: [];
                    
                    // Filter logs by date range
                    foreach ($fileLogs as $log) {
                        $logDate = substr($log['timestamp'], 0, 19);
                        if ($logDate >= $startDate && $logDate <= $endDate) {
                            $allLogs[] = $log;
                        }
                    }
                }
            }
        }
        
        // Sort logs by timestamp descending
        usort($allLogs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        $logs = $allLogs;
    }
}

// Log the export activity
logActivity('export_logs', 'system', 0, [
    'log_type' => $logType,
    'format' => $format,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'count' => count($logs)
]);

// Set filename
$filename = $logType . '_logs_' . date('Y-m-d') . '.' . $format;

// Export logs
if ($format === 'json') {
    // JSON export
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($logs, JSON_PRETTY_PRINT);
} else {
    // CSV export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($logType === 'audit') {
        // CSV headers for audit logs
        fputcsv($output, ['ID', 'User ID', 'Action', 'Entity Type', 'Entity ID', 'Details', 'IP Address', 'Created At']);
        
        // Write data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['user_id'],
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
                $log['details'],
                $log['ip_address'],
                $log['created_at']
            ]);
        }
    } else {
        // CSV headers for operation logs
        fputcsv($output, ['Timestamp', 'User ID', 'Action', 'Entity Type', 'Entity ID', 'Details', 'IP Address']);
        
        // Write data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['timestamp'],
                $log['user_id'],
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
                json_encode($log['details']),
                $log['ip_address']
            ]);
        }
    }
    
    // Close output stream
    fclose($output);
}

// Exit to prevent additional output
exit;
?>
