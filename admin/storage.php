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

// Calculate actual QR cache size
$qrCacheSize = 0;
if (file_exists(CACHE_DIR)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CACHE_DIR, FilesystemIterator::SKIP_DOTS)) as $file) {
        $qrCacheSize += $file->getSize();
    }
}

// Update QR size in storage usage
$storageUsage['qr_size'] = $qrCacheSize;
$storageUsage['total_size'] = $storageUsage['pdf_size'] + $qrCacheSize;

// Calculate storage usage percentage
$storagePercentage = ($storageUsage['total_size'] / MAX_STORAGE_SIZE) * 100;
$storagePercentage = min(100, max(0, $storagePercentage)); // Ensure between 0-100

// Get max upload size from settings
$maxUploadSize = getMaxUploadSize();

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Process QR cache cleaning from query string
if (isset($_GET['action']) && $_GET['action'] === 'clean_cache') {
    try {
        $db = getDbConnection();
        $deletedCount = 0;
        $totalSize = 0;
        
        // Get all QR files in cache directory
        $qrFiles = glob(CACHE_DIR . '*.png');
        
        // Get all document UUIDs from database
        $stmt = $db->query("SELECT uuid FROM documents");
        $documentUuids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($qrFiles as $qrFile) {
            // Extract UUID from filename (assuming format: UUID.png)
            $fileUuid = basename($qrFile, '.png');
            
            // If QR file doesn't have corresponding document, delete it
            if (!in_array($fileUuid, $documentUuids)) {
                $fileSize = filesize($qrFile);
                if (unlink($qrFile)) {
                    $deletedCount++;
                    $totalSize += $fileSize;
                }
            }
        }
        
        $message = "Cache cleaned successfully. Deleted {$deletedCount} unused QR files (" . formatBytes($totalSize) . ").";
        $messageType = 'success';
        
        // Log the action
        logActivity('clean_cache', 'system', 'qr_codes', [
            'deleted_count' => $deletedCount,
            'freed_space' => $totalSize
        ]);
        
        // Refresh storage usage statistics
        $storageUsage = getTotalStorageUsage();
        $storagePercentage = ($storageUsage['total_size'] / MAX_STORAGE_SIZE) * 100;
        $storagePercentage = min(100, max(0, $storagePercentage));
        
    } catch (Exception $e) {
        $message = 'Error cleaning QR cache: ' . $e->getMessage();
        $messageType = 'error';
        error_log($message);
    }
    
    // Redirect back to remove the action from URL
    header('Location: storage.php');
    exit;
}

// Process max file size update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_max_file_size'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
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
    <h1 class="text-2xl font-bold mb-6">Storage Usage</h1>

    <!-- Storage Usage Overview -->
    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <h2 class="text-xl font-bold mb-4">Storage Overview</h2>
        <div class="mb-4">
            <div class="flex justify-between mb-1">
                <span class="text-base font-medium text-blue-700">Storage Usage</span>
                <span class="text-sm font-medium text-blue-700"><?php echo formatBytes($storageUsage['total_size']); ?> / <?php echo formatBytes(MAX_STORAGE_SIZE); ?></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $storagePercentage; ?>%"></div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-lg border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Total Files</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo $totalDocuments; ?></p>
            </div>
            
            <div class="bg-white p-4 rounded-lg border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">PDF Storage</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo formatBytes($storageUsage['pdf_size']); ?></p>
            </div>
            
            <div class="bg-white p-4 rounded-lg border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">QR Code Storage</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo formatBytes($storageUsage['qr_size']); ?></p>
                <a href="?action=clean_cache" 
                   onclick="return confirm('Are you sure you want to clean unused QR code cache?')"
                   class="inline-block mt-2 bg-red-500 hover:bg-red-600 text-white text-sm py-1 px-2 rounded">
                    Clean Cache
                </a>
            </div>
        </div>
    </div>

    <!-- Maximum File Size Limit -->
    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <h2 class="text-xl font-bold mb-4">Maximum File Size Limit</h2>
        
        <?php if (isset($message)): ?>
            <div class="mb-4 p-4 rounded <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
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

    <!-- Document List -->
    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <h2 class="text-xl font-bold mb-4">Document Storage Details</h2>
        
        <?php if (!empty($documents)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Downloads</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($documents as $document): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($document['title']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($document['original_filename']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatBytes($document['file_size']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($document['views']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($document['downloads']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('Y-m-d H:i', strtotime($document['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-4 flex justify-center">
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $page === $i ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-gray-500">No documents found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'footer.php'; ?>
