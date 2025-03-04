<?php
/**
 * Audit Logs
 * 
 * Displays system audit logs and operation logs
 */

// Include required files
require_once '../includes/init.php';

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
        $logType = $_POST['log_type'] ?? 'audit';
        $dateRange = $_POST['date_range'] ?? 'all';
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        
        try {
            if ($logType === 'audit') {
                $db = getDbConnection();
                
                if ($dateRange === 'all') {
                    // Delete all audit logs
                    $db->exec("DELETE FROM audit_log");
                } else {
                    // Delete logs within date range
                    $stmt = $db->prepare("DELETE FROM audit_log WHERE DATE(created_at) BETWEEN :start_date AND :end_date");
                    $stmt->bindParam(':start_date', $startDate);
                    $stmt->bindParam(':end_date', $endDate);
                    $stmt->execute();
                }
                
                $successMessage = 'Audit logs cleared successfully.';
            } else {
                // Clear operation logs
                $logsDir = __DIR__ . '/../logs/operations';
                
                if ($dateRange === 'all') {
                    // Delete all operation log files
                    if (file_exists($logsDir)) {
                        $logFiles = glob($logsDir . '/*.json');
                        foreach ($logFiles as $file) {
                            @unlink($file);
                        }
                    }
                } else {
                    // Delete logs within date range
                    // For operations logs, we need to read each file, filter logs, and rewrite the file
                    if (file_exists($logsDir)) {
                        $logFiles = glob($logsDir . '/*.json');
                        $startTimestamp = strtotime($startDate);
                        $endTimestamp = strtotime($endDate) + 86399; // End of day
                        
                        foreach ($logFiles as $file) {
                            $fileDate = basename($file, '.json');
                            $fileDateTimestamp = strtotime($fileDate);
                            
                            // If file is within date range, process it
                            if ($fileDateTimestamp >= $startTimestamp && $fileDateTimestamp <= $endTimestamp) {
                                @unlink($file);
                            }
                        }
                    }
                }
                
                $successMessage = 'Operation logs cleared successfully.';
            }
            
            // Log the action
            logActivity('clear_logs', 'system', 0, [
                'log_type' => $logType,
                'date_range' => $dateRange,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        } catch (Exception $e) {
            $errorMessage = 'Error clearing logs: ' . $e->getMessage();
            logError($errorMessage);
        }
    }
}

// Get the log type (default to 'audit')
$logType = 'audit';

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get audit logs from database
$logs = [];
$totalLogs = 0;

$db = getDbConnection();

// Get total count for pagination
$totalLogs = $db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();

// Get logs with pagination
$stmt = $db->prepare("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate pagination
$totalPages = ceil($totalLogs / $perPage);

// Include header
$pageTitle = 'Audit Logs';
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><?php echo $pageTitle; ?></h1>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Export Logs</h2>
        <form action="export_logs.php" method="get" class="flex flex-wrap gap-4">
            <input type="hidden" name="type" value="audit">
            
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" 
                       class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo date('Y-m-d'); ?>" 
                       class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label for="format" class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                <select id="format" name="format" class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="csv">CSV</option>
                    <option value="json">JSON</option>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                    <i class="bi bi-download mr-2"></i> Export
                </button>
            </div>
        </form>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Clear Logs</h2>
        <form action="" method="post" class="flex flex-wrap gap-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="log_type" value="audit">
            
            <div>
                <label for="date_range" class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <select id="date_range" name="date_range" class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all">All</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            
            <div id="custom-date-range" style="display: none;">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" 
                           class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo date('Y-m-d'); ?>" 
                           class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="flex items-end">
                <button type="submit" name="clear_logs" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
                    <i class="bi bi-trash mr-2"></i> Clear Logs
                </button>
            </div>
        </form>
        
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
        
        <script>
            document.getElementById('date_range').addEventListener('change', function() {
                if (this.value === 'custom') {
                    document.getElementById('custom-date-range').style.display = 'block';
                } else {
                    document.getElementById('custom-date-range').style.display = 'none';
                }
            });
        </script>
    </div>
    
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            ID
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Timestamp
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Action
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Entity
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            IP Address
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Details
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            View
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                No logs found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $log['id']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $log['created_at']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $log['user_id'] ? 'User #' . $log['user_id'] : 'System'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($log['entity_type'] . ' #' . $log['entity_id']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($log['ip_address']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php 
                                        $details = json_decode($log['details'] ?? '', true);
                                        if (is_array($details) && !empty($details)) {
                                            echo '<ul class="list-disc list-inside">';
                                            foreach ($details as $key => $value) {
                                                if (is_scalar($value)) {
                                                    echo '<li><strong>' . htmlspecialchars((string)$key) . ':</strong> ' . htmlspecialchars((string)$value) . '</li>';
                                                }
                                            }
                                            echo '</ul>';
                                        } else {
                                            echo htmlspecialchars((string)($details ?? ''));
                                        }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="log_viewer.php?type=audit&id=<?php echo $log['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($totalPages > 1): ?>
        <div class="mt-6 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Previous</span>
                        <i class="bi bi-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Next</span>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include_once 'footer.php'; ?>
