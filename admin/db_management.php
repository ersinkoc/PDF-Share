<?php
/**
 * Reset Database Page
 * 
 * Allows administrators to backup, restore, and reset the database
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Initialize variables
$message = '';
$messageType = '';
$backups = [];
$selectedBackupStats = null;
$selectedBackupFile = '';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Get database statistics
$dbStats = getDatabaseStats();

// Get available backups
$backups = getAvailableBackups(BACKUPS_DIR);

// Process backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token. Please try again.');
        }
        
        // Create backup
        $backupFile = backupDatabase(BACKUPS_DIR);
        
        if ($backupFile) {
            $message = 'Database backup created successfully: ' . basename($backupFile);
            $messageType = 'success';
            
            // Refresh backup list
            $backups = getAvailableBackups(BACKUPS_DIR);
        } else {
            $message = 'Failed to create database backup.';
            $messageType = 'error';
        }
    } catch (Exception $e) {
        $message = 'Failed to create backup: ' . $e->getMessage();
        $messageType = 'error';
        error_log('Backup creation error: ' . $e->getMessage());
    }
}

// Process restore request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token. Please try again.');
        }
        
        if (!isset($_POST['backup_file']) || empty($_POST['backup_file'])) {
            $message = 'No backup file selected.';
            $messageType = 'error';
        } else {
            $backupFile = $_POST['backup_file'];
            
            // Validate backup file path (ensure it's within the backups directory)
            $realBackupPath = realpath($backupFile);
            $realBackupsDir = realpath(BACKUPS_DIR);
            
            if ($realBackupPath === false || strpos($realBackupPath, $realBackupsDir) !== 0) {
                $message = 'Invalid backup file path.';
                $messageType = 'error';
            } else {
                // Restore database
                $result = restoreDatabase($backupFile);
                
                if ($result) {
                    $message = 'Database restored successfully from: ' . basename($backupFile);
                    $messageType = 'success';
                    
                    // Refresh database stats
                    $dbStats = getDatabaseStats();
                } else {
                    $message = 'Failed to restore database.';
                    $messageType = 'error';
                }
            }
        }
    } catch (Exception $e) {
        $message = 'Failed to restore backup: ' . $e->getMessage();
        $messageType = 'error';
        error_log('Backup restore error: ' . $e->getMessage());
    }
}

// Process reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_database'])) {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token. Please try again.');
        }
        
        if (!isset($_POST['confirm_reset']) || $_POST['confirm_reset'] !== 'RESET') {
            $message = 'Please type RESET to confirm database reset.';
            $messageType = 'error';
        } else {
            // Backup settings before reset
            try {
                $db = getDbConnection();
                $stmt = $db->query("SELECT * FROM settings");
                $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $settingsBackup = json_encode($settings, JSON_PRETTY_PRINT);
                
                // Save settings to a temporary file
                $settingsBackupFile = BACKUPS_DIR . 'settings_backup_' . date('Y-m-d_H-i-s') . '.json';
                file_put_contents($settingsBackupFile, $settingsBackup);
            } catch (Exception $e) {
                error_log('Settings backup error: ' . $e->getMessage());
            }

            // Reset database
            $result = resetDatabase();
            
            if ($result) {
                // Restore settings if backup exists
                if (isset($settingsBackupFile) && file_exists($settingsBackupFile)) {
                    try {
                        $db = getDbConnection();
                        $restoredSettings = json_decode(file_get_contents($settingsBackupFile), true);
                        
                        // Restore each setting
                        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
                        foreach ($restoredSettings as $setting) {
                            $stmt->execute([$setting['setting_key'], $setting['setting_value']]);
                        }
                        
                        // Delete temporary settings backup
                        unlink($settingsBackupFile);
                        
                        $message = 'Database reset successfully. Settings were preserved and restored.';
                    } catch (Exception $e) {
                        error_log('Settings restore error: ' . $e->getMessage());
                        $message = 'Database reset successfully, but settings restoration failed. A backup was created automatically.';
                    }
                } else {
                    $message = 'Database reset successfully. A backup was created automatically.';
                }
                
                $messageType = 'success';
                
                // Refresh database stats
                $dbStats = getDatabaseStats();
                
                // Refresh backup list
                $backups = getAvailableBackups(BACKUPS_DIR);
            } else {
                $message = 'Failed to reset database.';
                $messageType = 'error';
            }
        }
    } catch (Exception $e) {
        $message = 'Failed to reset database: ' . $e->getMessage();
        $messageType = 'error';
        error_log('Database reset error: ' . $e->getMessage());
    }
}

// Process delete backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token. Please try again.');
        }
        
        if (!isset($_POST['backup_file']) || empty($_POST['backup_file'])) {
            $message = 'No backup file selected.';
            $messageType = 'error';
        } else {
            $backupFile = $_POST['backup_file'];
            
            // Validate backup file path (ensure it's within the backups directory)
            $realBackupPath = realpath($backupFile);
            $realBackupsDir = realpath(BACKUPS_DIR);
            
            if ($realBackupPath === false || strpos($realBackupPath, $realBackupsDir) !== 0) {
                $message = 'Invalid backup file path.';
                $messageType = 'error';
            } else {
                // Delete backup file
                if (unlink($backupFile)) {
                    $message = 'Backup file deleted successfully: ' . basename($backupFile);
                    $messageType = 'success';
                    
                    // Refresh backup list
                    $backups = getAvailableBackups(BACKUPS_DIR);
                } else {
                    $message = 'Failed to delete backup file.';
                    $messageType = 'error';
                }
            }
        }
    } catch (Exception $e) {
        $message = 'Failed to delete backup: ' . $e->getMessage();
        $messageType = 'error';
        error_log('Backup delete error: ' . $e->getMessage());
    }
}

// Process view backup stats request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_backup_stats'])) {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token. Please try again.');
        }
        
        if (!isset($_POST['backup_file']) || empty($_POST['backup_file'])) {
            $message = 'No backup file selected.';
            $messageType = 'error';
        } else {
            $backupFile = $_POST['backup_file'];
            
            // Validate backup file path
            $realBackupPath = realpath($backupFile);
            $realBackupsDir = realpath(BACKUPS_DIR);
            
            if ($realBackupPath === false || strpos($realBackupPath, $realBackupsDir) !== 0) {
                $message = 'Invalid backup file path.';
                $messageType = 'error';
            } else {
                // Get backup statistics
                $selectedBackupStats = getDatabaseStatsFromFile($backupFile);
                $selectedBackupFile = $backupFile;
                
                if (isset($selectedBackupStats['error'])) {
                    $message = 'Error analyzing backup file: ' . $selectedBackupStats['error'];
                    $messageType = 'error';
                }
            }
        }
    } catch (Exception $e) {
        $message = 'Failed to view backup statistics: ' . $e->getMessage();
        $messageType = 'error';
        error_log('Backup statistics error: ' . $e->getMessage());
    }
}

// Process backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_settings'])) {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token. Please try again.');
        }
        
        $db = getDbConnection();
        $stmt = $db->query("SELECT * FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settingsBackup = json_encode($settings, JSON_PRETTY_PRINT);
        
        // Save settings to backup file
        $settingsBackupFile = BACKUPS_DIR . 'settings_backup_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($settingsBackupFile, $settingsBackup);
        
        $message = 'Settings backup created successfully: ' . basename($settingsBackupFile);
        $messageType = 'success';
        
        // Log activity
        logActivity('backup', 'settings', basename($settingsBackupFile));
    } catch (Exception $e) {
        $message = 'Failed to create settings backup: ' . $e->getMessage();
        $messageType = 'error';
        error_log('Settings backup error: ' . $e->getMessage());
    }
}

// Process settings restore request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_settings'])) {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token. Please try again.');
        }
        
        if (!isset($_POST['settings_file']) || empty($_POST['settings_file'])) {
            $message = 'No settings backup file selected.';
            $messageType = 'error';
        } else {
            $settingsFile = $_POST['settings_file'];
            
            // Validate backup file path
            $realSettingsPath = realpath($settingsFile);
            $realBackupsDir = realpath(BACKUPS_DIR);
            
            if ($realSettingsPath === false || strpos($realSettingsPath, $realBackupsDir) !== 0) {
                $message = 'Invalid settings backup file path.';
                $messageType = 'error';
            } else {
                $db = getDbConnection();
                $restoredSettings = json_decode(file_get_contents($settingsFile), true);
                
                if ($restoredSettings === null) {
                    throw new Exception('Invalid JSON in settings backup file');
                }
                
                // Begin transaction
                $db->beginTransaction();
                
                try {
                    // Clear current settings
                    $db->exec("DELETE FROM settings");
                    
                    // Restore settings
                    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    foreach ($restoredSettings as $setting) {
                        $stmt->execute([$setting['setting_key'], $setting['setting_value']]);
                    }
                    
                    // Commit transaction
                    $db->commit();
                    
                    $message = 'Settings restored successfully from: ' . basename($settingsFile);
                    $messageType = 'success';
                    
                    // Log activity
                    logActivity('restore', 'settings', basename($settingsFile));
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $db->rollBack();
                    throw $e;
                }
            }
        }
    } catch (Exception $e) {
        $message = 'Failed to restore settings: ' . $e->getMessage();
        $messageType = 'error';
        error_log('Settings restore error: ' . $e->getMessage());
    }
}

// Process delete settings backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_settings_backup'])) {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token. Please try again.');
        }
        
        if (!isset($_POST['settings_file']) || empty($_POST['settings_file'])) {
            $message = 'No settings backup file selected.';
            $messageType = 'error';
        } else {
            $settingsFile = $_POST['settings_file'];
            
            // Validate backup file path
            $realSettingsPath = realpath($settingsFile);
            $realBackupsDir = realpath(BACKUPS_DIR);
            
            if ($realSettingsPath === false || strpos($realSettingsPath, $realBackupsDir) !== 0) {
                $message = 'Invalid settings backup file path.';
                $messageType = 'error';
            } else {
                if (unlink($settingsFile)) {
                    $message = 'Settings backup deleted successfully: ' . basename($settingsFile);
                    $messageType = 'success';
                    
                    // Log activity
                    logActivity('delete', 'settings', basename($settingsFile));
                } else {
                    $message = 'Failed to delete settings backup file.';
                    $messageType = 'error';
                }
            }
        }
    } catch (Exception $e) {
        $message = 'Failed to delete settings backup: ' . $e->getMessage();
        $messageType = 'error';
        error_log('Settings backup delete error: ' . $e->getMessage());
    }
}

if (isset($_GET['download']) && !empty($_GET['file'])) {
    $filename = basename($_GET['file']);
    $downloadPath = downloadBackupFile('db', $filename);
    
    if ($downloadPath) {
        streamFileDownload($downloadPath, $filename);
        exit;
    } else {
        $_SESSION['error'] = "Failed to download backup file."; 
        header("Location: db_management.php");
        exit;
    }
}

// Include header
$pageTitle = 'Reset Database';
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Database Management</h1>
    
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Database Backup Section -->
    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <h2 class="text-xl font-bold mb-4">Database Backup</h2>
        <p class="mb-4 text-gray-600">
            Create a backup of the current database. Backups are stored in the <code>backups</code> directory.
        </p>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="mb-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <button type="submit" name="create_backup" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Create Backup
            </button>
        </form>
        
        <?php if (!empty($backups)): ?>
            <h3 class="text-lg font-semibold mb-2">Available Backups</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 border-b text-left">Filename</th>
                            <th class="py-2 px-4 border-b text-left">Created</th>
                            <th class="py-2 px-4 border-b text-left">Size</th>
                            <th class="py-2 px-4 border-b text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($backup['filename']); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($backup['created']); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo formatFileSize($backup['size']); ?></td>
                                <td class="py-2 px-4 border-b">
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['path']); ?>">
                                        <button type="submit" name="restore_backup" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline mr-1" onclick="return confirm('Are you sure you want to restore this backup? Current data will be replaced.')">
                                            Restore
                                        </button>
                                        <button type="submit" name="delete_backup" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline" onclick="return confirm('Are you sure you want to delete this backup?')">
                                            Delete
                                        </button>
                                        <button type="submit" name="view_backup_stats" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline" onclick="return confirm('Are you sure you want to view the statistics of this backup?')">
                                            View Stats
                                        </button>
                                    </form>
                                    <div class="text-end">
                                        <a href="db_management.php?download=1&file=<?php echo urlencode($backup['filename']); ?>" 
                                           class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
                <p>No backups available.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Database Reset Section -->
    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <h2 class="text-xl font-bold mb-4">Reset Database</h2>
        <p class="mb-4 text-gray-600">
            Reset the database to its initial state. This will delete all data and recreate the database structure.
            A backup will be created automatically before resetting.
        </p>
        
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
            <p class="font-bold">Warning!</p>
            <p>This action cannot be undone. All data will be permanently deleted.</p>
        </div>
        
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4">
            <p class="font-bold">Note:</p>
            <p>Your system settings will be automatically preserved and restored after the reset.</p>
        </div>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="mb-6">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_reset">
                    Type "RESET" to confirm:
                </label>
                <input type="text" id="confirm_reset" name="confirm_reset" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            
            <button type="submit" name="reset_database" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" onclick="return confirm('Are you absolutely sure you want to reset the database? ALL DATA WILL BE LOST!')">
                Reset Database
            </button>
        </form>
    </div>
    
    
    <!-- Selected Backup Statistics Section -->
    <?php if ($selectedBackupStats !== null): ?>
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-xl font-bold mb-4">Selected Backup Statistics</h2>
            <p class="mb-4 text-gray-600">
                Statistics for the selected backup file: <?php echo htmlspecialchars(basename($selectedBackupFile)); ?>
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-semibold mb-2">Backup File</h3>
                    <p><strong>Path:</strong> <?php echo htmlspecialchars($selectedBackupFile); ?></p>
                    <p><strong>Size:</strong> <?php echo formatFileSize(filesize($selectedBackupFile)); ?></p>
                    <p><strong>Last Modified:</strong> <?php echo date('Y-m-d H:i:s', filemtime($selectedBackupFile)); ?></p>
                </div>
                
                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-semibold mb-2">Backup Content</h3>
                    <p><strong>Tables:</strong> <?php echo count($selectedBackupStats['tables']); ?></p>
                    <p><strong>Total Records:</strong> <?php echo array_sum(array_column($selectedBackupStats['tables'], 'row_count')); ?></p>
                </div>
            </div>
            
            <h3 class="text-lg font-semibold mb-2">Database Comparison</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 border-b text-left">Table Name</th>
                            <th class="py-2 px-4 border-b text-left">Current Records</th>
                            <th class="py-2 px-4 border-b text-left">Backup Records</th>
                            <th class="py-2 px-4 border-b text-left">Difference</th>
                            <th class="py-2 px-4 border-b text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Combine all table names from both current and backup
                        $allTables = array_unique(array_merge(
                            array_keys($dbStats['tables']), 
                            array_keys($selectedBackupStats['tables'])
                        ));
                        sort($allTables);
                        
                        foreach ($allTables as $tableName): 
                            $currentCount = isset($dbStats['tables'][$tableName]) ? 
                                ($dbStats['tables'][$tableName]['row_count'] ?? 0) : 0;
                            $backupCount = isset($selectedBackupStats['tables'][$tableName]) ? 
                                ($selectedBackupStats['tables'][$tableName]['row_count'] ?? 0) : 0;
                            $difference = $currentCount - $backupCount;
                            
                            // Determine status
                            if (!isset($dbStats['tables'][$tableName])) {
                                $status = 'Table missing in current DB';
                                $statusClass = 'text-red-600';
                            } elseif (!isset($selectedBackupStats['tables'][$tableName])) {
                                $status = 'Table missing in backup';
                                $statusClass = 'text-yellow-600';
                            } elseif ($difference > 0) {
                                $status = 'More records in current DB';
                                $statusClass = 'text-blue-600';
                            } elseif ($difference < 0) {
                                $status = 'More records in backup';
                                $statusClass = 'text-purple-600';
                            } else {
                                $status = 'Identical';
                                $statusClass = 'text-green-600';
                            }
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($tableName); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo number_format($currentCount); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo number_format($backupCount); ?></td>
                                <td class="py-2 px-4 border-b">
                                    <?php if ($difference > 0): ?>
                                        <span class="text-blue-600">+<?php echo number_format($difference); ?></span>
                                    <?php elseif ($difference < 0): ?>
                                        <span class="text-red-600"><?php echo number_format($difference); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-600">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-4 border-b">
                                    <span class="<?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-6 flex justify-between">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($selectedBackupFile); ?>">
                    <button type="submit" name="restore_backup" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" onclick="return confirm('Are you sure you want to restore this backup? Current data will be replaced.')">
                        <i class="bi bi-arrow-counterclockwise mr-2"></i> Restore This Backup
                    </button>
                </form>
                
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="bi bi-x-circle mr-2"></i> Close Comparison
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once 'footer.php'; ?>
