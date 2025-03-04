<?php
/**
 * Export Logs
 * 
 * Exports audit logs in CSV or JSON format
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Get parameters
$format = isset($_GET['format']) && $_GET['format'] === 'json' ? 'json' : 'csv';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    // Get database connection
    $db = getDbConnection();
    
    // Get logs within date range
    $stmt = $db->prepare("SELECT * FROM audit_log WHERE DATE(created_at) BETWEEN :start_date AND :end_date ORDER BY created_at DESC");
    $stmt->execute([
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for download
    $filename = 'audit_logs_' . date('Y-m-d_H-i-s');
    
    if ($format === 'json') {
        // Export as JSON
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        
        // Convert details to array
        foreach ($logs as &$log) {
            $log['details'] = json_decode($log['details'], true);
        }
        
        echo json_encode($logs, JSON_PRETTY_PRINT);
    } else {
        // Export as CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        fputcsv($output, [
            'ID',
            'UUID',
            'User ID',
            'User UUID',
            'Action',
            'Entity Type',
            'Entity ID',
            'Entity UUID',
            'IP Address',
            'Created At',
            'Details'
        ]);
        
        // Write data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['uuid'],
                $log['user_id'],
                $log['user_uuid'],
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
                $log['entity_uuid'],
                $log['ip_address'],
                $log['created_at'],
                $log['details']
            ]);
        }
        
        fclose($output);
    }
    
    // Log the export
    logActivity('export_logs', 'audit_log', 0, [
        'format' => $format,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'count' => count($logs)
    ]);
} catch (Exception $e) {
    die('Error exporting logs: ' . $e->getMessage());
}
