<?php
/**
 * Storage Usage
 * 
 * Displays storage usage statistics
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Get storage usage statistics
$storageUsage = getTotalStorageUsage();

// Calculate storage usage percentage
$storagePercentage = ($storageUsage['total_size'] / MAX_STORAGE_SIZE) * 100;
$storagePercentage = min(100, max(0, $storagePercentage)); // Ensure between 0-100

// Get document list with file sizes
$db = getDbConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Get pagination setting from database
$settingStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'general.items_per_page'");
$setting = $settingStmt->fetch(PDO::FETCH_ASSOC);
$perPage = ($setting && !empty($setting['setting_value'])) ? (int)$setting['setting_value'] : 10;

$offset = ($page - 1) * $perPage;

// Count total documents
$countStmt = $db->query("SELECT COUNT(*) FROM documents");
$totalDocuments = $countStmt->fetchColumn();
$totalPages = ceil($totalDocuments / $perPage);

// Get document list with file sizes and pagination
$stmt = $db->prepare("SELECT d.id, d.uuid, d.title, d.filename, d.original_filename, d.file_size, d.created_at,
                      (SELECT COUNT(*) FROM views WHERE document_uuid = d.uuid) as views,
                      COALESCE(s.downloads, 0) as downloads
                      FROM documents d
                      LEFT JOIN stats s ON d.uuid = s.document_uuid
                      ORDER BY d.file_size DESC
                      LIMIT :offset, :limit");
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
$stmt->execute();
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
$pageTitle = 'Storage Usage';
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Storage Usage</h1>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Total Storage</h2>
            <div class="text-right">
                <p class="text-2xl font-bold"><?php echo $storageUsage['formatted_size']; ?> / <?php echo formatBytes(MAX_STORAGE_SIZE); ?></p>
                <p class="text-sm text-gray-500"><?php echo $storageUsage['total_files']; ?> files</p>
            </div>
        </div>
        
        <div class="w-full bg-gray-200 rounded-full h-4 mb-4">
            <div class="bg-blue-600 h-4 rounded-full" style="width: <?php echo $storagePercentage; ?>%"></div>
        </div>
        
        <p class="text-sm text-gray-500 text-right"><?php echo number_format($storagePercentage, 1); ?>% used</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Average File Size</h3>
            <p class="text-3xl font-bold">
                <?php 
                    echo $storageUsage['total_files'] > 0 
                        ? formatBytes($storageUsage['total_size'] / $storageUsage['total_files']) 
                        : '0 B'; 
                ?>
            </p>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Largest File</h3>
            <p class="text-3xl font-bold">
                <?php 
                    echo !empty($documents) 
                        ? formatBytes($documents[0]['file_size']) 
                        : '0 B'; 
                ?>
            </p>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Files by Size
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            File Name
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Size
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Uploaded
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Views
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Downloads
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                No files found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($doc['original_filename']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatBytes($doc['file_size']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($doc['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $doc['views']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $doc['downloads']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?php echo BASE_URL; ?>admin/edit.php?uuid=<?php echo $doc['uuid']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>view.php?uuid=<?php echo $doc['uuid']; ?>&admin=1" class="text-blue-600 hover:text-blue-900 mr-3" target="_blank">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>admin/delete.php?uuid=<?php echo $doc['uuid']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this file?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium"><?php echo min(($page - 1) * $perPage + 1, $totalDocuments); ?></span>
                            to
                            <span class="font-medium"><?php echo min($page * $perPage, $totalDocuments); ?></span>
                            of
                            <span class="font-medium"><?php echo $totalDocuments; ?></span>
                            results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, min($page - 2, $totalPages - 4));
                            $endPage = min($totalPages, max($page + 2, 5));
                            
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo ($i === $page) ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
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
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="mt-8">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Maximum File Size Limit
                </h3>
            </div>
            <div class="p-6">
                <?php
                // Get max upload size from settings
                $maxUploadSize = getMaxUploadSize();
                
                // Process form submission to update max file size
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_max_file_size'])) {
                    // Validate CSRF token
                    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                        $message = 'Invalid CSRF token. Please try again.';
                        $messageType = 'error';
                    } else {
                        // Get the new max file size
                        $newMaxFileSize = (int)$_POST['max_file_size'];
                        $sizeUnit = $_POST['size_unit'];
                        
                        // Convert to bytes based on unit
                        switch ($sizeUnit) {
                            case 'KB':
                                $newMaxFileSize *= 1024;
                                break;
                            case 'MB':
                                $newMaxFileSize *= 1024 * 1024;
                                break;
                            case 'GB':
                                $newMaxFileSize *= 1024 * 1024 * 1024;
                                break;
                        }
                        
                        // Validate the size (must be positive and not exceed MAX_STORAGE_SIZE)
                        if ($newMaxFileSize <= 0) {
                            $message = 'File size must be greater than zero.';
                            $messageType = 'error';
                        } elseif ($newMaxFileSize > MAX_STORAGE_SIZE) {
                            $message = 'File size cannot exceed total storage limit (' . formatBytes(MAX_STORAGE_SIZE) . ').';
                            $messageType = 'error';
                        } else {
                            try {
                                // Update the setting in the database
                                $db = getDbConnection();
                                $stmt = $db->prepare("UPDATE settings SET setting_value = :value, updated_at = CURRENT_TIMESTAMP WHERE setting_key = 'upload.max_file_size'");
                                $stmt->bindParam(':value', $newMaxFileSize, PDO::PARAM_INT);
                                $result = $stmt->execute();
                                
                                if ($result) {
                                    $message = 'Maximum file size updated successfully.';
                                    $messageType = 'success';
                                    
                                    // Log the setting change
                                    logActivity('update', 'setting', 'upload.max_file_size', [
                                        'old_value' => $maxUploadSize,
                                        'new_value' => $newMaxFileSize
                                    ]);
                                    
                                    // Update the variable for display
                                    $maxUploadSize = $newMaxFileSize;
                                } else {
                                    $message = 'Failed to update maximum file size.';
                                    $messageType = 'error';
                                }
                            } catch (Exception $e) {
                                $message = 'Error updating maximum file size: ' . $e->getMessage();
                                $messageType = 'error';
                                error_log("Error updating max file size: " . $e->getMessage());
                            }
                        }
                    }
                }
                
                // Calculate percentage of max file size compared to total storage
                $fileSizePercentage = min(100, ($maxUploadSize / MAX_STORAGE_SIZE) * 100);
                ?>
                
                <?php if (isset($message)): ?>
                    <div class="mb-4 p-4 rounded <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="max_file_size">
                            Maximum File Size Per Upload
                        </label>
                        <div class="flex items-center">
                            <input type="number" 
                                id="max_file_size" 
                                name="max_file_size" 
                                value="<?php 
                                    // Convert bytes to appropriate unit for display
                                    $displaySize = $maxUploadSize;
                                    $displayUnit = 'B';
                                    
                                    if ($displaySize >= 1024 * 1024 * 1024) {
                                        $displaySize = round($displaySize / (1024 * 1024 * 1024), 2);
                                        $displayUnit = 'GB';
                                    } elseif ($displaySize >= 1024 * 1024) {
                                        $displaySize = round($displaySize / (1024 * 1024), 2);
                                        $displayUnit = 'MB';
                                    } elseif ($displaySize >= 1024) {
                                        $displaySize = round($displaySize / 1024, 2);
                                        $displayUnit = 'KB';
                                    }
                                    
                                    echo $displaySize;
                                ?>" 
                                class="shadow appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline mr-2"
                                min="0.1"
                                step="0.1"
                                required>
                            
                            <select 
                                id="size_unit" 
                                name="size_unit" 
                                class="shadow border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="KB" <?php echo $displayUnit === 'KB' ? 'selected' : ''; ?>>KB</option>
                                <option value="MB" <?php echo $displayUnit === 'MB' ? 'selected' : ''; ?>>MB</option>
                                <option value="GB" <?php echo $displayUnit === 'GB' ? 'selected' : ''; ?>>GB</option>
                            </select>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Current server limit: <?php echo formatBytes(min(convertPHPSizeToBytes(ini_get('upload_max_filesize')), convertPHPSizeToBytes(ini_get('post_max_size')))); ?></p>
                    </div>
                    
                    <div class="bg-gray-100 rounded-full h-4 mb-4">
                        <div class="bg-green-500 h-4 rounded-full" style="width: <?php echo $fileSizePercentage; ?>%"></div>
                    </div>
                    <div class="text-sm text-gray-500 mb-4">
                        Maximum file size: <?php echo formatBytes($maxUploadSize); ?> (<?php echo number_format($fileSizePercentage, 1); ?>% of total storage)
                    </div>
                    
                    <button type="submit" name="update_max_file_size" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Update Maximum File Size
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once 'footer.php'; ?>
