<?php
/**
 * Admin Settings Page
 * 
 * Allows administrators to manage application settings
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Initialize variables
$message = '';
$messageType = '';
$groupedSettings = [];

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Get all settings first
try {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM settings ORDER BY setting_key");
    $stmt->execute();
    $settingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Define prefixes to exclude
    $excludedPrefixes = [
        's3.',
        'system.',
        // 'qrcode.'
        // Add your other prefixes here
        // Example: 'aws.', 'secret.' etc.
    ];
    
    // Group settings by category, excluding settings with specified prefixes
    foreach ($settingsData as $setting) {
        // Check if setting starts with any excluded prefix
        $shouldExclude = false;
        foreach ($excludedPrefixes as $prefix) {
            if (strpos($setting['setting_key'], $prefix) === 0) {
                $shouldExclude = true;
                break;
            }
        }
        
        // Skip if setting should be excluded
        if ($shouldExclude) {
            continue;
        }
        
        $parts = explode('.', $setting['setting_key']);
        $category = $parts[0];
        if (!isset($groupedSettings[$category])) {
            $groupedSettings[$category] = [];
        }
        $groupedSettings[$category][] = $setting;
    }
} catch (Exception $e) {
    $message = 'Error retrieving settings: ' . $e->getMessage();
    $messageType = 'error';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug log
    error_log("Settings form submitted: " . json_encode($_POST));
    
    // Check if backup_settings button was clicked
    if (isset($_POST['backup_settings'])) {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $message = 'Invalid CSRF token. Please try again.';
            $messageType = 'error';
        } else {
            try {
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
    }
    // Check if restore_settings button was clicked
    else if (isset($_POST['restore_settings'])) {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $message = 'Invalid CSRF token. Please try again.';
            $messageType = 'error';
        } else if (!isset($_POST['settings_file']) || empty($_POST['settings_file'])) {
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
                try {
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
                        
                        // Refresh settings data
                        $stmt = $db->prepare("SELECT * FROM settings ORDER BY setting_key");
                        $stmt->execute();
                        $settingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Re-group settings
                        $groupedSettings = [];
                        foreach ($settingsData as $setting) {
                            // Check if setting starts with any excluded prefix
                            $shouldExclude = false;
                            foreach ($excludedPrefixes as $prefix) {
                                if (strpos($setting['setting_key'], $prefix) === 0) {
                                    $shouldExclude = true;
                                    break;
                                }
                            }
                            
                            // Skip if setting should be excluded
                            if ($shouldExclude) {
                                continue;
                            }
                            
                            $parts = explode('.', $setting['setting_key']);
                            $category = $parts[0];
                            if (!isset($groupedSettings[$category])) {
                                $groupedSettings[$category] = [];
                            }
                            $groupedSettings[$category][] = $setting;
                        }
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $db->rollBack();
                        throw $e;
                    }
                } catch (Exception $e) {
                    $message = 'Failed to restore settings: ' . $e->getMessage();
                    $messageType = 'error';
                    error_log('Settings restore error: ' . $e->getMessage());
                }
            }
        }
    }
    // Check if delete_settings_backup button was clicked
    else if (isset($_POST['delete_settings_backup'])) {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $message = 'Invalid CSRF token. Please try again.';
            $messageType = 'error';
        } else if (!isset($_POST['settings_file']) || empty($_POST['settings_file'])) {
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
    }
    // Check if save_settings button was clicked
    else if (isset($_POST['save_settings'])) {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $message = 'Invalid CSRF token. Please try again.';
            $messageType = 'error';
            error_log("CSRF token validation failed. Received: " . ($_POST['csrf_token'] ?? 'none') . ", Expected: " . ($csrfToken ?? 'none'));
        } else {
            // Get database connection
            $db = getDbConnection();
            
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Get all settings to check which ones are editable
                $stmt = $db->query('SELECT setting_key, is_editable FROM settings');
                $editableSettings = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $editableSettings[$row['setting_key']] = $row['is_editable'];
                }
                
                // Debug available settings
                error_log("Available settings in database: " . json_encode(array_keys($editableSettings)));
                
                // Process each POST field
                foreach ($_POST as $key => $value) {
                    // Skip non-setting fields
                    if (in_array($key, ['csrf_token', 'save_settings'])) {
                        continue;
                    }
                    
                    // Convert form field name back to setting key
                    // Fix the conversion to handle different formats
                    if (strpos($key, '_') !== false) {
                        // Only replace the first underscore with a dot
                        $pos = strpos($key, '_');
                        $settingKey = substr($key, 0, $pos) . '.' . substr($key, $pos + 1);
                        
                        // Debug the key transformation
                        error_log("Form field: {$key} -> Setting key: {$settingKey}");
                    } else {
                        $settingKey = $key;
                        error_log("Form field: {$key} -> Setting key: {$settingKey} (no transformation)");
                    }
                    
                    // Check if setting starts with any excluded prefix
                    $shouldExclude = false;
                    foreach ($excludedPrefixes as $prefix) {
                        if (strpos($settingKey, $prefix) === 0) {
                            $shouldExclude = true;
                            break;
                        }
                    }
                    
                    // Skip if setting should be excluded
                    if ($shouldExclude) {
                        error_log("Setting key '{$settingKey}' is excluded by prefix rules.");
                        continue;
                    }
                    
                    // Skip if setting is not editable
                    if (!isset($editableSettings[$settingKey]) || $editableSettings[$settingKey] == 0) {
                        error_log("Setting key '{$settingKey}' is not editable.");
                        continue;
                    }
                    
                    try {
                        // Update the setting
                        $stmt = $db->prepare('UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?');
                        $result = $stmt->execute([$value, $settingKey]);
                        
                        if ($result) {
                            error_log("Updated setting: {$settingKey} = {$value}");
                        } else {
                            error_log("Failed to update setting: {$settingKey}");
                        }
                    } catch (Exception $e) {
                        error_log("Error updating setting {$settingKey}: " . $e->getMessage());
                    }
                }
                
                // Commit transaction
                $db->commit();
                
                // Log the action
                logActivity('UPDATE', 'settings', 'multiple', ['action' => 'Settings updated']);
                
                $message = 'Settings updated successfully.';
                $messageType = 'success';
                
                // Generate new CSRF token
                $csrfToken = generateCSRFToken();
                
                // Refresh settings data
                $stmt = $db->prepare("SELECT * FROM settings ORDER BY setting_key");
                $stmt->execute();
                $settingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Re-group settings
                $groupedSettings = [];
                foreach ($settingsData as $setting) {
                    // Check if setting starts with any excluded prefix
                    $shouldExclude = false;
                    foreach ($excludedPrefixes as $prefix) {
                        if (strpos($setting['setting_key'], $prefix) === 0) {
                            $shouldExclude = true;
                            break;
                        }
                    }
                    
                    // Skip if setting should be excluded
                    if ($shouldExclude) {
                        continue;
                    }
                    
                    $parts = explode('.', $setting['setting_key']);
                    $category = $parts[0];
                    if (!isset($groupedSettings[$category])) {
                        $groupedSettings[$category] = [];
                    }
                    $groupedSettings[$category][] = $setting;
                }
            } catch (Exception $e) {
                // Rollback transaction
                $db->rollBack();
                
                $message = 'Error saving settings: ' . $e->getMessage();
                $messageType = 'error';
                error_log("Settings error: " . $e->getMessage());
            }
        }
    }
}

if (isset($_GET['download']) && !empty($_GET['file'])) {
    $filename = basename($_GET['file']);
    
    try {
        // Create backup directory if not exists
        if (!file_exists(BACKUPS_DIR)) {
            mkdir(BACKUPS_DIR, 0755, true);
        }
        
        // Get backup file path
        $backupPath = BACKUPS_DIR . $filename;
        
        // Check if file exists
        if (!file_exists($backupPath)) {
            throw new Exception('Backup file not found.');
        }
        
        // Stream file to user
        streamFileDownload($backupPath, $filename);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to download settings backup file: " . $e->getMessage();
        header("Location: settings.php");
        exit;
    }
}

// Include header
$pageTitle = 'Settings';
include_once 'header.php';

// Debug information
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    echo '<div class="container mx-auto px-4 py-2 bg-gray-100 text-xs">';
    echo '<h3 class="font-bold">Debug Info:</h3>';
    echo '<pre>REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD'] . '</pre>';
    echo '<pre>POST data: ' . htmlspecialchars(json_encode($_POST, JSON_PRETTY_PRINT)) . '</pre>';
    echo '<pre>CSRF Token: ' . ($csrfToken ?? 'Not set') . '</pre>';
    echo '</div>';
}
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Application Settings</h1>
    
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        <?php if (!empty($groupedSettings)): ?>
            <?php foreach ($groupedSettings as $category => $settings): ?>
                <div class="bg-white shadow-md rounded-lg overflow-hidden mb-4">
                    <div class="border-b border-gray-200">
                        <div class="bg-gray-50 px-4 py-3">
                            <h2 class="text-lg font-medium text-gray-700 capitalize"><?php echo ucfirst($category); ?> Settings</h2>
                        </div>
                        <div class="p-4">
                            <?php foreach ($settings as $setting): ?>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="<?php echo str_replace('.', '_', $setting['setting_key']); ?>">
                                        <?php echo $setting['setting_description'] ?: ucfirst(str_replace('.', ' ', $setting['setting_key'])); ?>
                                        <?php if (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0): ?>
                                            <span class="ml-2 text-xs bg-gray-200 text-gray-700 py-1 px-2 rounded">System Managed</span>
                                        <?php endif; ?>
                                    </label>
                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                        <select 
                                            id="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            name="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0) ? 'disabled' : ''; ?>
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0) ? 'bg-gray-100' : ''; ?>">
                                            <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Yes</option>
                                            <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>No</option>
                                        </select>
                                    <?php elseif ($setting['setting_type'] === 'email'): ?>
                                        <input type="email" 
                                            id="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            name="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                            <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0) ? 'readonly' : ''; ?>
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0) ? 'bg-gray-100' : ''; ?>">
                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                        <input type="number" 
                                            id="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            name="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                            <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0) ? 'readonly' : ''; ?>
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0) ? 'bg-gray-100' : ''; ?>">
                                    <?php elseif ($setting['setting_type'] === 'textarea'): ?>
                                        <textarea 
                                            id="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            name="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0) ? 'readonly' : ''; ?>
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0) ? 'bg-gray-100' : ''; ?>"
                                            rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                    <?php else: ?>
                                        <input type="text" 
                                            id="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            name="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                            <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0) ? 'readonly' : ''; ?>
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0) ? 'bg-gray-100' : ''; ?>">
                                    <?php endif; ?>
                                    <?php if (isset($setting['is_editable']) && $setting['is_editable'] == 0 || strpos($setting['setting_key'], 'system.') === 0): ?>
                                        <p class="mt-1 text-xs text-gray-500">This setting is managed by the system and cannot be edited manually.</p>
                                    <?php endif; ?>
                                    <?php if ($setting['setting_key'] === 'upload.max_file_size'): ?>
                                        <p class="text-sm text-gray-500 mt-1">Current value: <?php echo formatBytes((int)$setting['setting_value']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo $setting['setting_key']; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="mt-6 flex justify-end">
                <button type="submit" name="save_settings" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Save Settings
                </button>
            </div>
        <?php else: ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
                <p>No settings found.</p>
            </div>
        <?php endif; ?>
    </form>

    <!-- Settings Backup Management -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden mt-8">
        <div class="border-b border-gray-200">
            <div class="bg-gray-50 px-4 py-3">
                <h2 class="text-lg font-medium text-gray-700">Settings Backup Management</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Create and manage backups of your system settings. You can restore settings from these backups when needed.
                </p>
            </div>
        </div>

        <div class="p-4">
            <div class="flex space-x-4 mb-6">
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button type="submit" name="backup_settings" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Create Settings Backup
                    </button>
                </form>
            </div>

            <?php
            // List available settings backups
            $settingsBackups = glob(BACKUPS_DIR . 'settings_backup_*.json');
            if (!empty($settingsBackups)):
            ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 border-b text-left">Backup File</th>
                                <th class="py-2 px-4 border-b text-left">Created</th>
                                <th class="py-2 px-4 border-b text-left">Size</th>
                                <th class="py-2 px-4 border-b text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($settingsBackups as $settingsBackup): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-4 border-b"><?php echo basename($settingsBackup); ?></td>
                                    <td class="py-2 px-4 border-b"><?php echo date('Y-m-d H:i:s', filemtime($settingsBackup)); ?></td>
                                    <td class="py-2 px-4 border-b"><?php echo formatFileSize(filesize($settingsBackup)); ?></td>
                                    <td class="py-2 px-4 border-b">
                                        <form method="post" class="inline-block">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="settings_file" value="<?php echo htmlspecialchars($settingsBackup); ?>">
                                            <button type="submit" name="restore_settings" 
                                                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline mr-1"
                                                    onclick="return confirm('Are you sure you want to restore these settings? Current settings will be replaced.')">
                                                Restore
                                            </button>
                                            <button type="submit" name="delete_settings_backup" 
                                                    class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline"
                                                    onclick="return confirm('Are you sure you want to delete this settings backup?')">
                                                Delete
                                            </button>
                                        </form>
                                        <a href="settings.php?download=1&file=<?php echo urlencode(basename($settingsBackup)); ?>" 
                                           class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline inline-block">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
                    <p>No settings backups available.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once 'footer.php'; ?>
