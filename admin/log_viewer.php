<?php
/**
 * Log Viewer
 * 
 * Displays detailed information about a specific log entry
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Get log ID
$logId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($logId <= 0) {
    header('Location: logs.php');
    exit;
}

// Get log details
try {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM audit_log WHERE id = ?");
    $stmt->execute([$logId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        header('Location: logs.php');
        exit;
    }
    
    // Get user details if user_id exists
    $user = null;
    if ($log['user_id']) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$log['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get entity details if entity_type is 'document'
    $entity = null;
    if ($log['entity_type'] === 'document' && $log['entity_id']) {
        $stmt = $db->prepare("SELECT * FROM documents WHERE id = ?");
        $stmt->execute([$log['entity_id']]);
        $entity = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    die('Error retrieving log details: ' . $e->getMessage());
}

// Include header
$pageTitle = 'Log Details';
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Log Details #<?php echo $logId; ?></h1>
        <a href="logs.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
            <i class="bi bi-arrow-left mr-2"></i> Back to Logs
        </a>
    </div>
    
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h2 class="text-lg font-semibold mb-4">Basic Information</h2>
                    <table class="min-w-full">
                        <tr>
                            <td class="py-2 text-gray-600 font-medium">ID:</td>
                            <td class="py-2 text-gray-900"><?php echo $log['id']; ?></td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600 font-medium">UUID:</td>
                            <td class="py-2 text-gray-900"><?php echo $log['uuid']; ?></td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600 font-medium">Timestamp:</td>
                            <td class="py-2 text-gray-900"><?php echo $log['created_at']; ?></td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600 font-medium">Action:</td>
                            <td class="py-2 text-gray-900"><?php echo htmlspecialchars($log['action'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600 font-medium">IP Address:</td>
                            <td class="py-2 text-gray-900"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div>
                    <h2 class="text-lg font-semibold mb-4">User Information</h2>
                    <?php if ($user && !empty($user['id'])): ?>
                        <table class="min-w-full">
                            <tr>
                                <td class="py-2 text-gray-600 font-medium">User ID:</td>
                                <td class="py-2 text-gray-900"><?php echo $user['id']; ?></td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-600 font-medium">Username:</td>
                                <td class="py-2 text-gray-900"><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-600 font-medium">Email:</td>
                                <td class="py-2 text-gray-900"><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-600 font-medium">Role:</td>
                                <td class="py-2 text-gray-900"><?php echo isset($user['role']) ? htmlspecialchars($user['role']) : 'N/A'; ?></td>
                            </tr>
                        </table>
                    <?php else: ?>
                        <p class="text-gray-500">System action or user no longer exists.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-6">
                <h2 class="text-lg font-semibold mb-4">Entity Information</h2>
                <table class="min-w-full">
                    <tr>
                        <td class="py-2 text-gray-600 font-medium">Entity Type:</td>
                        <td class="py-2 text-gray-900"><?php echo htmlspecialchars($log['entity_type'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600 font-medium">Entity ID:</td>
                        <td class="py-2 text-gray-900"><?php echo $log['entity_id'] ?? 'N/A'; ?></td>
                    </tr>
                    <?php if ($entity && !empty($entity)): ?>
                        <tr>
                            <td class="py-2 text-gray-600 font-medium">Entity Details:</td>
                            <td class="py-2 text-gray-900">
                                <pre class="bg-gray-100 p-4 rounded overflow-x-auto"><?php echo htmlspecialchars(json_encode($entity, JSON_PRETTY_PRINT)); ?></pre>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <div class="mt-6">
                <h2 class="text-lg font-semibold mb-4">Additional Details</h2>
                <?php
                $details = json_decode($log['details'], true);
                if (is_array($details) && !empty($details)):
                ?>
                    <div class="bg-gray-100 p-4 rounded overflow-x-auto">
                        <pre class="text-sm"><?php echo htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT)); ?></pre>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500">No additional details available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once 'footer.php'; ?>
