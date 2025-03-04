<?php
/**
 * S3 Backup Management
 * 
 * Allows administrators to manage S3 backups
 */

// Include required files
require_once '../includes/init.php';
require_once '../includes/classes/S3Storage.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Initialize S3 storage
$s3Storage = new S3Storage();

// Initialize variables
$s3Message = '';
$s3MessageType = '';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Get S3 settings
$db = getDbConnection();
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 's3.%' OR setting_key = 's3.provider'");
$s3Settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $s3Settings[$row['setting_key']] = $row['setting_value'];
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_s3_settings'])) {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token. Please try again.');
        }

        // Update settings
        $stmt = $db->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = :key");
        
        $settingsToUpdate = [
            's3.provider' => $_POST['s3_provider'],
            's3.endpoint' => $_POST['s3_endpoint'],
            's3.bucket' => $_POST['s3_bucket'],
            's3.region' => $_POST['s3_region'],
            's3.access_key' => $_POST['s3_access_key'],
            's3.secret_key' => $_POST['s3_secret_key'],
            's3.use_path_style' => isset($_POST['s3_use_path_style']) ? '1' : '0',
            's3.ssl_verify' => isset($_POST['s3_ssl_verify']) ? '1' : '0'
        ];

        foreach ($settingsToUpdate as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value]);
        }

        $s3Message = 'S3 settings updated successfully.';
        $s3MessageType = 'success';

        // Update settings array for display
        $s3Settings = array_merge($s3Settings, $settingsToUpdate);

        // Log activity for settings update
        logActivity('update', 'settings', 'S3 settings', array(
            'action' => 'Settings updated',
            'status' => 'success'
        ));
    } catch (Exception $e) {
        $s3Message = 'Failed to update S3 settings: ' . $e->getMessage();
        $s3MessageType = 'error';
        error_log('S3 settings update error: ' . $e->getMessage());
    }
}

// Handle S3 operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['backup_to_s3'])) {
        try {
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token. Please try again.');
            }
            
            // Create backup directory if not exists
            if (!file_exists(BACKUPS_DIR)) {
                mkdir(BACKUPS_DIR, 0755, true);
            }
            
            // Create backup file
            $backupName = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
            $backupPath = BACKUPS_DIR . $backupName;
            
            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($backupPath, ZipArchive::CREATE) === TRUE) {
                // Add database file
                $zip->addFile(DB_PATH, 'database.sqlite');
                
                // Add PDF files
                $uploadDir = BASE_PATH . 'uploads/';
                if (file_exists($uploadDir)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($uploadDir),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    
                    foreach ($files as $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = substr($filePath, strlen($uploadDir));
                            $zip->addFile($filePath, 'uploads/' . $relativePath);
                        }
                    }
                }
                
                $zip->close();
                
                // Upload to S3
                $s3Storage->backupToS3($backupPath, $backupName);
                
                // Delete local backup file
                unlink($backupPath);
                
                $s3Message = 'Backup successfully uploaded to S3.';
                $s3MessageType = 'success';
                
                // Log activity for backup
                logActivity('backup', 'storage', $backupName, array(
                    'action' => 'Backup created',
                    'status' => 'success',
                    'location' => 'S3'
                ));
            } else {
                throw new Exception('Failed to create backup archive.');
            }
        } catch (Exception $e) {
            $s3Message = 'Backup failed: ' . $e->getMessage();
            $s3MessageType = 'error';
            error_log('S3 backup error: ' . $e->getMessage());
        }
    } elseif (isset($_POST['restore_from_s3'])) {
        try {
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token. Please try again.');
            }
            
            $backupName = $_POST['backup_file'];
            
            // Create temporary directory for restoration
            $tempDir = BACKUPS_DIR . 'restore_' . time() . '/';
            mkdir($tempDir, 0755, true);
            
            // Download backup from S3
            $backupPath = $tempDir . $backupName;
            $s3Storage->restoreFromS3($backupName, $backupPath);
            
            // Extract backup
            $zip = new ZipArchive();
            if ($zip->open($backupPath) === TRUE) {
                $zip->extractTo($tempDir);
                $zip->close();
                
                // Stop PDO connection
                $db = null;
                
                // Restore database
                copy($tempDir . 'database.sqlite', DB_PATH);
                
                // Restore PDF files
                $uploadDir = BASE_PATH . 'uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Copy uploaded files
                if (file_exists($tempDir . 'uploads/')) {
                    recursiveCopy($tempDir . 'uploads/', $uploadDir);
                }
                
                // Clean up
                recursiveDelete($tempDir);
                
                $s3Message = 'Backup successfully restored from S3.';
                $s3MessageType = 'success';
                
                // Log activity for restore
                logActivity('restore', 'storage', $backupName, array(
                    'action' => 'Backup restored',
                    'status' => 'success',
                    'source' => 'S3'
                ));
            } else {
                throw new Exception('Failed to extract backup archive.');
            }
        } catch (Exception $e) {
            $s3Message = 'Restore failed: ' . $e->getMessage();
            $s3MessageType = 'error';
            error_log('S3 restore error: ' . $e->getMessage());
        }
    } elseif (isset($_POST['delete_backups'])) {
        try {
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token. Please try again.');
            }

            // Validate selected backups
            if (!isset($_POST['backups_to_delete']) || empty($_POST['backups_to_delete'])) {
                throw new Exception('No backups selected for deletion.');
            }

            // Delete selected backups
            $deletedCount = 0;
            foreach ($_POST['backups_to_delete'] as $backupName) {
                $s3Key = 'backups/' . $backupName;
                $s3Storage->deleteFile($s3Key);
                $deletedCount++;
                
                // Log activity for delete
                logActivity('delete', 'storage', $backupName, array(
                    'action' => 'Backup deleted',
                    'status' => 'success',
                    'location' => 'S3'
                ));
            }

            $s3Message = sprintf('%d backup(s) successfully deleted.', $deletedCount);
            $s3MessageType = 'success';
        } catch (Exception $e) {
            $s3Message = 'Failed to delete backups: ' . $e->getMessage();
            $s3MessageType = 'error';
            error_log('S3 delete backups error: ' . $e->getMessage());
        }
    }
}

// Get S3 backups list
$s3Backups = [];
if ($s3Storage->isConfigured()) {
    try {
        $s3Backups = $s3Storage->listBackups();
    } catch (Exception $e) {
        $s3Message = 'Failed to list backups: ' . $e->getMessage();
        $s3MessageType = 'error';
        error_log('S3 list backups error: ' . $e->getMessage());
    }
}

if (isset($_GET['download']) && !empty($_GET['file'])) {
    $filename = basename($_GET['file']);
    
    try {
        // Create backup directory if not exists
        if (!file_exists(BACKUPS_DIR)) {
            mkdir(BACKUPS_DIR, 0755, true);
        }
        
        // Download from S3 to local backup directory
        $localPath = BACKUPS_DIR . $filename;
        $s3Key = 'backups/' . $filename;
        
        $s3Storage->downloadFile($s3Key, $localPath);
        
        // Stream file to user
        streamFileDownload($localPath, $filename);
        
        // Clean up local file after download
        unlink($localPath);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to download S3 backup file: " . $e->getMessage();
        header("Location: s3_backup.php");
        exit;
    }
}

// Include header
$pageTitle = 'S3 Backup Management';
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">S3 Backup Management</h1>
    
    <?php if (!empty($s3Message)): ?>
        <div class="mb-4 p-4 rounded <?php echo $s3MessageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo $s3Message; ?>
        </div>
    <?php endif; ?>

    <!-- S3 Settings -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                S3 Settings
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                Configure your S3 storage settings here.
            </p>
        </div>
        <div class="p-6">
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="grid grid-cols-1 gap-6">
                    <!-- Storage Provider -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Storage Provider</label>
                        <select name="s3_provider" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="minio" <?php echo ($s3Settings['s3.provider'] ?? '') === 'minio' ? 'selected' : ''; ?>>MinIO</option>
                            <option value="s3" <?php echo ($s3Settings['s3.provider'] ?? '') === 's3' ? 'selected' : ''; ?>>Amazon S3</option>
                        </select>
                    </div>

                    <!-- S3 Endpoint -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">S3 Endpoint</label>
                        <input type="text" name="s3_endpoint" value="<?php echo htmlspecialchars($s3Settings['s3.endpoint'] ?? ''); ?>" 
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <!-- S3 Bucket -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">S3 Bucket</label>
                        <input type="text" name="s3_bucket" value="<?php echo htmlspecialchars($s3Settings['s3.bucket'] ?? ''); ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <!-- S3 Region -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">S3 Region</label>
                        <input type="text" name="s3_region" value="<?php echo htmlspecialchars($s3Settings['s3.region'] ?? ''); ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <!-- S3 Access Key -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">S3 Access Key</label>
                        <input type="text" name="s3_access_key" value="<?php echo htmlspecialchars($s3Settings['s3.access_key'] ?? ''); ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <!-- S3 Secret Key -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">S3 Secret Key</label>
                        <input type="password" name="s3_secret_key" value="<?php echo htmlspecialchars($s3Settings['s3.secret_key'] ?? ''); ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <!-- Checkboxes -->
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input type="checkbox" name="s3_use_path_style" value="1" 
                                       <?php echo ($s3Settings['s3.use_path_style'] ?? '') === '1' ? 'checked' : ''; ?>
                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label class="font-medium text-gray-700">Use Path Style</label>
                                <p class="text-gray-500">Use path-style addressing instead of virtual hosted-style</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input type="checkbox" name="s3_ssl_verify" value="1"
                                       <?php echo ($s3Settings['s3.ssl_verify'] ?? '') === '1' ? 'checked' : ''; ?>
                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label class="font-medium text-gray-700">SSL Verify</label>
                                <p class="text-gray-500">Verify SSL certificates when connecting to S3</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" name="update_s3_settings" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Update S3 Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($s3Storage->isConfigured()): ?>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    S3 Backup Management
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Create and manage backups of your database and files in S3 storage.
                </p>
            </div>
            <div class="p-6">
                <div class="mb-6">
                    <form method="post" class="inline-block">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <button type="submit" name="backup_to_s3" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Create New Backup
                        </button>
                    </form>
                </div>
                
                <?php if (!empty($s3Backups)): ?>
                    <form method="post" id="delete-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" id="select-all" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Backup Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Size
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($s3Backups as $backup): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="checkbox" name="backups_to_delete[]" value="<?php echo basename($backup['key']); ?>" 
                                                   class="backup-checkbox focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo basename($backup['key']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo formatBytes($backup['size']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $backup['modified']->format('Y-m-d H:i:s'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <form method="post" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="backup_file" value="<?php echo basename($backup['key']); ?>">
                                                <button type="submit" name="restore_from_s3" 
                                                        class="text-indigo-600 hover:text-indigo-900 mr-2"
                                                        onclick="return confirm('Are you sure you want to restore this backup? This will overwrite your current database and files.');">
                                                    Restore
                                                </button>
                                            </form>
                                            <a href="s3_backup.php?download=1&file=<?php echo urlencode(basename($backup['key'])); ?>" 
                                               class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="mt-4">
                            <button type="submit" name="delete_backups" 
                                    class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded"
                                    onclick="return confirm('Are you sure you want to delete the selected backups? This action cannot be undone.');">
                                Delete Selected Backups
                            </button>
                        </div>
                    </form>

                    <script>
                        document.getElementById('select-all').addEventListener('change', function() {
                            document.querySelectorAll('.backup-checkbox').forEach(checkbox => {
                                checkbox.checked = this.checked;
                            });
                        });
                    </script>
                <?php else: ?>
                    <p class="text-gray-500">No backups found in S3.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    S3 Configuration Required
                </h3>
            </div>
            <div class="p-6">
                <p class="text-gray-500">Please configure S3 settings in the Settings page to enable backup management.</p>
                <a href="settings.php" class="mt-4 inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Configure S3 Settings
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include_once 'footer.php'; ?> 