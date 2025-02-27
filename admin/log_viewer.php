<?php
/**
 * Log Viewer
 * 
 * Displays detailed view of a specific log entry
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Get the log type and ID
$logType = isset($_GET['type']) && $_GET['type'] === 'operations' ? 'operations' : 'audit';
$logId = isset($_GET['id']) ? $_GET['id'] : null;
$logDate = isset($_GET['date']) ? $_GET['date'] : null;
$logIndex = isset($_GET['index']) ? intval($_GET['index']) : null;

// Get log details
$log = null;

if ($logType === 'audit' && $logId) {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM audit_log WHERE id = :id");
    $stmt->bindParam(':id', $logId);
    $stmt->execute();
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($log) {
        // Decode details
        $log['details_decoded'] = json_decode($log['details'], true) ?: [];
    }
} elseif ($logType === 'operations' && $logDate && $logIndex !== null) {
    $logFile = __DIR__ . '/../logs/operations/' . $logDate . '.json';
    if (file_exists($logFile)) {
        $fileContent = file_get_contents($logFile);
        if (!empty($fileContent)) {
            $logs = json_decode($fileContent, true) ?: [];
            if (isset($logs[$logIndex])) {
                $log = $logs[$logIndex];
            }
        }
    }
}

// Include header
$pageTitle = 'Log Details';
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Log Details</h1>
        <a href="logs.php?type=<?php echo $logType; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
            <i class="bi bi-arrow-left mr-2"></i> Back to Logs
        </a>
    </div>
    
    <?php if (!$log): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <p>Log entry not found.</p>
        </div>
        
        <a href="logs.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Return to Logs
        </a>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    <?php echo ucfirst($logType); ?> Log Details
                </h3>
            </div>
            <div class="p-6">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
                    <?php if ($logType === 'audit'): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">ID</dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo $log['id']; ?></dd>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Timestamp</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $logType === 'audit' ? $log['created_at'] : $log['timestamp']; ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">User ID</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $log['user_id'] ?: 'System'; ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Action</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($log['action']); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Entity Type</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($log['entity_type']); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Entity ID</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($log['entity_id']); ?></dd>
                    </div>
                    
                    <div>
                        <dt class="text-sm font-medium text-gray-500">IP Address</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($log['ip_address']); ?></dd>
                    </div>
                </dl>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Details
                </h3>
            </div>
            <div class="p-6">
                <?php 
                    $details = $logType === 'audit' ? $log['details_decoded'] : $log['details'];
                    
                    if (is_array($details) && !empty($details)):
                ?>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Key
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Value
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($details as $key => $value): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($key); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?php 
                                                if (is_array($value)) {
                                                    echo '<pre class="text-xs">' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT)) . '</pre>';
                                                } elseif (is_bool($value)) {
                                                    echo $value ? 'true' : 'false';
                                                } else {
                                                    echo htmlspecialchars($value);
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-gray-500 italic">No additional details available.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once 'footer.php'; ?>
