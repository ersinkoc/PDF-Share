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

// Generate CSRF token if not exists
if (!isset($_SESSION['settings_csrf_token'])) {
    $_SESSION['settings_csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['settings_csrf_token_time'] = time();
}

// Get all settings first
try {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM settings ORDER BY setting_key");
    $stmt->execute();
    $settingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group settings by category
    foreach ($settingsData as $setting) {
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
    
    // Check if save_settings button was clicked
    if (isset($_POST['save_settings'])) {
        // Validate CSRF token
        if (!isset($_SESSION['settings_csrf_token']) || $_POST['csrf_token'] !== $_SESSION['settings_csrf_token']) {
            $message = 'Invalid CSRF token. Please try again.';
            $messageType = 'error';
            error_log("CSRF token validation failed. Received: " . ($_POST['csrf_token'] ?? 'none') . ", Expected: " . ($_SESSION['settings_csrf_token'] ?? 'none'));
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
                    
                    // Skip if setting is not editable
                    if (!isset($editableSettings[$settingKey])) {
                        error_log("Setting key '{$settingKey}' does not exist in database.");
                        continue;
                    }
                    
                    if ($editableSettings[$settingKey] == 0) {
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
                $_SESSION['settings_csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['settings_csrf_token_time'] = time();
                
                // Refresh settings data
                $stmt = $db->prepare("SELECT * FROM settings ORDER BY setting_key");
                $stmt->execute();
                $settingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Re-group settings
                $groupedSettings = [];
                foreach ($settingsData as $setting) {
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

// Include header
$pageTitle = 'Settings';
include_once 'header.php';

// Debug information
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    echo '<div class="container mx-auto px-4 py-2 bg-gray-100 text-xs">';
    echo '<h3 class="font-bold">Debug Info:</h3>';
    echo '<pre>REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD'] . '</pre>';
    echo '<pre>POST data: ' . htmlspecialchars(json_encode($_POST, JSON_PRETTY_PRINT)) . '</pre>';
    echo '<pre>CSRF Token: ' . ($_SESSION['settings_csrf_token'] ?? 'Not set') . '</pre>';
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
    
    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['settings_csrf_token']; ?>">
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
                                        <?php if (isset($setting['is_editable']) && $setting['is_editable'] == 0): ?>
                                            <span class="ml-2 text-xs bg-gray-200 text-gray-700 py-1 px-2 rounded">System Managed</span>
                                        <?php endif; ?>
                                    </label>
                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                        <select 
                                            id="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            name="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0) ? 'disabled' : ''; ?>
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0) ? 'bg-gray-100' : ''; ?>">
                                            <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Yes</option>
                                            <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>No</option>
                                        </select>
                                    <?php elseif ($setting['setting_type'] === 'email'): ?>
                                        <input type="email" 
                                            id="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            name="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                            <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0) ? 'readonly' : ''; ?>
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0) ? 'bg-gray-100' : ''; ?>">
                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                        <input type="number" 
                                            id="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            name="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                            <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0) ? 'readonly' : ''; ?>
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0) ? 'bg-gray-100' : ''; ?>">
                                    <?php elseif ($setting['setting_type'] === 'textarea'): ?>
                                        <textarea 
                                            id="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            name="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0) ? 'readonly' : ''; ?>
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0) ? 'bg-gray-100' : ''; ?>"
                                            rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                    <?php else: ?>
                                        <input type="text" 
                                            id="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            name="<?php echo str_replace('.', '_', $setting['setting_key']); ?>" 
                                            value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                            <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0) ? 'readonly' : ''; ?>
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (isset($setting['is_editable']) && $setting['is_editable'] == 0) ? 'bg-gray-100' : ''; ?>">
                                    <?php endif; ?>
                                    <?php if (isset($setting['is_editable']) && $setting['is_editable'] == 0): ?>
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
        <?php else: ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">
                <p>No settings found. Please check database connection.</p>
            </div>
        <?php endif; ?>
        
        <div class="mt-6">
            <button type="submit" name="save_settings" value="1" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Save Settings
            </button>
        </div>
    </form>
</div>

<?php include_once 'footer.php'; ?>
