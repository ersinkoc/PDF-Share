<?php
/**
 * Utility Functions
 * 
 * Common utility functions used throughout the application
 */

// Include configuration
require_once __DIR__ . '/config.php';

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    // Check if session exists
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        return false;
    }
    
    // Check for session timeout
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        // Session expired, destroy it
        session_destroy();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Check if user is admin
 * 
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    // Check if user is logged in
    if (!isLoggedIn()) {
        return false;
    }
    
    // Check if user has admin privileges
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Generate a unique ID for filenames
 * 
 * @return string Unique ID
 */
function generateUniqueId() {
    return bin2hex(random_bytes(8)) . '_' . time();
}

/**
 * Generate a short URL code
 * 
 * @param int $length Length of the code
 * @return string Short URL code
 */
function generateShortUrl($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    // Check if code already exists in database
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE short_url = :code");
    $stmt->bindParam(':code', $code);
    $stmt->execute();
    
    if ($stmt->fetchColumn() > 0) {
        // Code already exists, generate a new one
        return generateShortUrl($length);
    }
    
    return $code;
}

/**
 * Generate a QR code for a URL
 * 
 * @param string $url URL to encode in QR code
 * @return string URL to QR code image
 */
function generateQRCode($url) {
    try {
        // Use QR code library
        require_once BASE_PATH . 'vendor/autoload.php';
        
        // Get QR code size from settings (default to 5 if not set)
        $qrSize = (int)getSettingValue('qrcode.size', 5);
        
        // Create QR code options
        $options = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => \chillerlan\QRCode\QRCode::ECC_L,
            'scale' => max(1, min($qrSize, 20)), // Ensure size is between 1 and 20
            'imageBase64' => false,
            'imageTransparent' => false,
            'drawLightModules' => true,
            'moduleValues' => [
                // Finder Pattern - Dark (outer)
                \chillerlan\QRCode\Data\QRMatrix::M_FINDER => [0, 0, 0],
                // Finder Pattern - Dark (inner dot)
                \chillerlan\QRCode\Data\QRMatrix::M_FINDER_DOT => [0, 0, 0],
                // Alignment Pattern
                \chillerlan\QRCode\Data\QRMatrix::M_ALIGNMENT => [0, 0, 0],
                // Timing Pattern
                \chillerlan\QRCode\Data\QRMatrix::M_TIMING => [0, 0, 0],
                // Format Information
                \chillerlan\QRCode\Data\QRMatrix::M_FORMAT => [0, 0, 0],
                // Version Information
                \chillerlan\QRCode\Data\QRMatrix::M_VERSION => [0, 0, 0],
                // Data Module
                \chillerlan\QRCode\Data\QRMatrix::M_DATA => [0, 0, 0],
                // Dark Module
                \chillerlan\QRCode\Data\QRMatrix::M_DARKMODULE => [0, 0, 0],
                // Quiet Zone
                \chillerlan\QRCode\Data\QRMatrix::M_QUIETZONE => [255, 255, 255],
            ],
        ]);
        
        // Generate unique filename
        $urlHash = md5($url);
        $cacheFile = CACHE_DIR . $urlHash . '.png';
        $cacheUrl = BASE_URL . 'cache/' . $urlHash . '.png';
        
        // Check cache directory
        if (!file_exists(CACHE_DIR)) {
            mkdir(CACHE_DIR, 0755, true);
        }
        
        // Return from cache if exists and size hasn't changed
        if (file_exists($cacheFile)) {
                return $cacheUrl;
        }
        
        // Generate and save QR code
        $qrcode = new \chillerlan\QRCode\QRCode($options);
        $qrcode->render($url, $cacheFile);
        
        return $cacheUrl;
    } catch (Exception $e) {
        // Log error
        error_log("QR code generation failed: " . $e->getMessage());
        
        // Return placeholder image
        return BASE_URL . 'assets/img/qr-placeholder.png';
    }
}

/**
 * Format file size in human-readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Format file size in human readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get file upload error message
 * 
 * @param int $errorCode PHP file upload error code
 * @return string Error message
 */
function getFileUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}

/**
 * Get upload error message
 * 
 * @param int $errorCode PHP upload error code
 * @return string Error message
 */
function uploadErrorMessage($errorCode) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    
    return $errors[$errorCode] ?? 'Unknown upload error';
}

/**
 * Get maximum upload size
 * 
 * @return int Maximum upload size in bytes
 */
function getMaxUploadSize() {
    // Check if max upload size is set in settings
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'upload.max_file_size'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['setting_value'])) {
            return (int)$result['setting_value'];
        }
    } catch (Exception $e) {
        // Ignore database errors and continue with PHP limits
    }
    
    // Get PHP upload limits
    $maxUpload = convertPHPSizeToBytes(ini_get('upload_max_filesize'));
    $maxPost = convertPHPSizeToBytes(ini_get('post_max_size'));
    $memoryLimit = convertPHPSizeToBytes(ini_get('memory_limit'));
    
    // Return the smallest of the three
    return min($maxUpload, $maxPost, $memoryLimit);
}

/**
 * Convert PHP size to bytes
 * 
 * @param string $size PHP size string (e.g. 2M, 8M, 1G)
 * @return int Size in bytes
 */
function convertPHPSizeToBytes($size) {
    if (empty($size)) {
        return 0;
    }
    
    $unit = strtoupper(substr($size, -1));
    $value = (int)substr($size, 0, -1);
    
    switch ($unit) {
        case 'G':
            $value *= 1024;
        case 'M':
            $value *= 1024;
        case 'K':
            $value *= 1024;
    }
    
    return $value;
}

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token Token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals((string)$_SESSION['csrf_token'], (string)$token);
}

/**
 * Detect device type from user agent
 * 
 * @return string Device type (mobile or desktop)
 */
function detectDeviceType() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tt\-|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($userAgent, 0, 4))) {
        return 'mobile';
    }
    
    return 'desktop';
}

/**
 * Log error message
 * 
 * @param string $message Error message
 */
function logError($message) {
    if (!file_exists(LOGS_DIR)) {
        mkdir(LOGS_DIR, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
    
    file_put_contents(ERROR_LOG, $logMessage, FILE_APPEND);
}

/**
 * Get total storage usage
 * 
 * @return array Total storage usage information
 */
function getTotalStorageUsage() {
    try {
        $db = getDbConnection();
        $stmt = $db->query("SELECT SUM(file_size) as total_size, COUNT(*) as total_files FROM documents");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalSize = $result['total_size'] ?: 0;
        $totalFiles = $result['total_files'] ?: 0;
        
        // Get max storage size from constant
        $maxStorage = getSettingValue('storage.max_space');
        
        // Calculate percentage
        $percent = min(100, ($totalSize / $maxStorage) * 100);
        
        // Calculate actual QR code cache size
        $qrSize = 0;
        if (defined('CACHE_DIR') && file_exists(CACHE_DIR)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CACHE_DIR, FilesystemIterator::SKIP_DOTS)) as $file) {
                $qrSize += $file->getSize();
            }
        }
        
        // PDF size is total size of documents
        $pdfSize = $totalSize;
        
        // Total size includes both PDF and QR
        $totalSize = $pdfSize + $qrSize;
        
        return [
            'total_size' => $totalSize,
            'total_files' => $totalFiles,
            'formatted_size' => formatBytes($totalSize),
            'used_formatted' => formatBytes($totalSize),
            'total_formatted' => formatBytes($maxStorage),
            'percent' => $percent,
            'pdf_size' => $pdfSize,
            'qr_size' => $qrSize
        ];
    } catch (Exception $e) {
        return [
            'total_size' => 0,
            'total_files' => 0,
            'formatted_size' => '0 B',
            'used_formatted' => '0 B',
            'total_formatted' => formatBytes(defined('MAX_STORAGE_SIZE') ? MAX_STORAGE_SIZE : (1024 * 1024 * 1024)),
            'percent' => 0,
            'pdf_size' => 0,
            'qr_size' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Log an activity to the audit log
 * 
 * @param string $action The action performed
 * @param string $entity_type The type of entity (document, user, setting)
 * @param int|string $entity_id The ID of the entity
 * @param array $details Additional details about the action
 * @param int|null $user_id The ID of the user who performed the action
 * @return bool True if logged successfully, false otherwise
 */
function logActivity($action, $entity_type, $entity_id, $details = [], $user_id = null) {
    try {
        $db = getDbConnection();
        
        // Get the current user ID if not provided
        if ($user_id === null && isset($_SESSION) && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        // Check if user exists in the database
        if ($user_id !== null && $user_id > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() == 0) {
                $user_id = null; // User doesn't exist, set to null
            }
        }
        
        // Set default user ID for CLI or when session is not available
        if ($user_id === null) {
            $user_id = null; // Use NULL for system actions
        }
        
        // Get IP address
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';
        
        // Convert details to JSON
        $details_json = json_encode($details);
        
        // Generate UUID for the log entry
        $uuid = generateUUID();
        
        // Get user UUID if user_id is provided
        $user_uuid = null;
        if ($user_id !== null) {
            $stmt = $db->prepare("SELECT uuid FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_uuid = $stmt->fetchColumn();
        }
        
        // Get entity UUID if entity_type is 'document' and entity_id is numeric
        $entity_uuid = null;
        if ($entity_type === 'document' && is_numeric($entity_id)) {
            $stmt = $db->prepare("SELECT uuid FROM documents WHERE id = ?");
            $stmt->execute([$entity_id]);
            $entity_uuid = $stmt->fetchColumn();
        }
        
        // Prepare the SQL statement
        $stmt = $db->prepare("INSERT INTO audit_log (uuid, user_id, user_uuid, action, entity_type, entity_id, entity_uuid, details, ip_address, created_at) 
                             VALUES (:uuid, :user_id, :user_uuid, :action, :entity_type, :entity_id, :entity_uuid, :details, :ip_address, CURRENT_TIMESTAMP)");
        
        // Bind parameters
        $stmt->bindParam(':uuid', $uuid);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_uuid', $user_uuid);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':entity_type', $entity_type);
        $stmt->bindParam(':entity_id', $entity_id);
        $stmt->bindParam(':entity_uuid', $entity_uuid);
        $stmt->bindParam(':details', $details_json);
        $stmt->bindParam(':ip_address', $ip_address);
        
        // Execute the statement
        return $stmt->execute();
    } catch (Exception $e) {
        // Log error to file
        error_log("Failed to log activity: " . $e->getMessage());
        
        // Try to log to file as fallback
        return logOperationToFile($action, $entity_type, $entity_id, $details, $user_id);
    }
}

/**
 * Log an operation to a JSON file
 * 
 * @param string $action The action performed
 * @param string $entity_type The type of entity
 * @param int|string $entity_id The ID of the entity
 * @param array $details Additional details
 * @param int|null $user_id The user ID
 * @param string $ip_address The IP address
 * @return bool True if logged successfully, false otherwise
 */
function logOperationToFile($action, $entity_type, $entity_id, $details = [], $user_id = null, $ip_address = 'unknown') {
    try {
        // Create logs directory if it doesn't exist
        $logsDir = __DIR__ . '/../logs/operations';
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        // Create a log entry
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $user_id,
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'details' => $details,
            'ip_address' => $ip_address
        ];
        
        // Determine the filename based on the date
        $filename = $logsDir . '/' . date('Y-m-d') . '.json';
        
        // Read existing logs if the file exists
        $logs = [];
        if (file_exists($filename)) {
            $fileContent = file_get_contents($filename);
            if (!empty($fileContent)) {
                $logs = json_decode($fileContent, true) ?: [];
            }
        }
        
        // Add the new log entry
        $logs[] = $logEntry;
        
        // Write the logs back to the file
        file_put_contents($filename, json_encode($logs, JSON_PRETTY_PRINT));
        
        return true;
    } catch (Exception $e) {
        logError('Failed to log operation to file: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log out user and record the activity
 */
function logoutUser() {
    // Log the logout activity if user is logged in
    if (isset($_SESSION['user_id'])) {
        logActivity('logout', 'user', $_SESSION['user_id'], [
            'username' => $_SESSION['username'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    // Clear session
    $_SESSION = [];
    
    // Destroy the session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Generate a UUID v4
 * 
 * @return string UUID v4
 */
function generateUUID() {
    // Generate 16 bytes (128 bits) of random data
    $data = random_bytes(16);
    
    // Set version to 0100 (UUID v4)
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    
    // Format the UUID
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Check if a string is a valid UUID
 * 
 * @param string $uuid String to check
 * @return bool True if valid UUID, false otherwise
 */
function isValidUUID($uuid) {
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
}

/**
 * Get document by UUID
 * 
 * @param string $uuid Document UUID
 * @return array|false Document data or false if not found
 */
function getDocumentByUUID($uuid) {
    if (!isValidUUID($uuid)) {
        return false;
    }
    
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM documents WHERE uuid = :uuid");
    $stmt->bindParam(':uuid', $uuid);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get user by UUID
 * 
 * @param string $uuid User UUID
 * @return array|false User data or false if not found
 */
function getUserByUUID($uuid) {
    if (!isValidUUID($uuid)) {
        return false;
    }
    
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE uuid = :uuid");
    $stmt->bindParam(':uuid', $uuid);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Reset database to initial state
 * 
 * @return bool True if reset was successful
 */
function resetDatabase() {
    try {
        // Create a backup before reset
        $backupFile = backupDatabase(BACKUPS_DIR);
        if (!$backupFile) {
            throw new Exception("Failed to create safety backup before reset");
        }
        
        // Get database connection
        $db = getDbConnection();
        
        // Close the database connection to allow file operations
        $db = null;
        
        // Delete the database file
        if (file_exists(DB_PATH)) {
            if (!unlink(DB_PATH)) {
                throw new Exception("Failed to delete database file");
            }
        }
        
        // Reconnect to database (this will create a new empty database)
        $db = getDbConnection();
        
        // Log the reset operation
        logActivity('RESET', 'database', basename(DB_PATH), [
            'backup_file' => basename($backupFile)
        ]);
        
        return true;
    } catch (Exception $e) {
        logError("Database reset failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get database statistics
 * 
 * Returns statistics about the database
 * 
 * @return array Database statistics
 */
function getDatabaseStats() {
    try {
        $db = getDbConnection();
        $stats = [];
        
        // Get database file size
        $dbPath = DB_PATH;
        $stats['database_size'] = file_exists($dbPath) ? filesize($dbPath) : 0;
        $stats['database_size_formatted'] = formatFileSize($stats['database_size']);
        
        // Get list of all tables
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Initialize tables array as associative array
        $stats['tables'] = [];
        
        foreach ($allTables as $table) {
            try {
                $tableStats = [
                    'name' => $table,
                    'row_count' => 0,  // Changed from 'rows' to 'row_count' for consistency
                    'columns' => [],
                    'column_count' => 0,
                    'indexes' => [],
                    'index_count' => 0,
                    'size' => 0 // Approximate size, not accurate in SQLite
                ];
                
                // Get row count
                $stmt = $db->prepare("SELECT COUNT(*) FROM \"$table\"");
                $stmt->execute();
                $tableStats['row_count'] = (int)$stmt->fetchColumn();
                
                // Get table info
                $stmt = $db->prepare("PRAGMA table_info(\"$table\")");
                $stmt->execute();
                $tableStats['columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $tableStats['column_count'] = count($tableStats['columns']);
                
                // Get index info
                $stmt = $db->prepare("PRAGMA index_list(\"$table\")");
                $stmt->execute();
                $tableStats['indexes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $tableStats['index_count'] = count($tableStats['indexes']);
                
                // Estimate table size based on row count and column count
                // This is a very rough estimate as SQLite doesn't provide table size info
                $tableStats['size'] = $tableStats['row_count'] * $tableStats['column_count'] * 100; // Rough estimate
                
                // Add table stats to tables array with table name as key
                $stats['tables'][$table] = $tableStats;
            } catch (Exception $tableException) {
                // Log error but continue with other tables
                logError("Error getting stats for table $table: " . $tableException->getMessage());
            }
        }
        
        // Get total document size if documents table exists
        try {
            if (in_array('documents', $allTables)) {
                $stmt = $db->prepare("SELECT SUM(file_size) FROM documents");
                $stmt->execute();
                $stats['total_document_size'] = $stmt->fetchColumn() ?: 0;
                $stats['total_document_size_formatted'] = formatFileSize($stats['total_document_size']);
            } else {
                $stats['total_document_size'] = 0;
                $stats['total_document_size_formatted'] = '0 B';
            }
        } catch (Exception $e) {
            $stats['total_document_size'] = 0;
            $stats['total_document_size_formatted'] = '0 B';
            logError('Error getting document size: ' . $e->getMessage());
        }
        
        // Get storage statistics
        $stats['storage'] = getStorageStats();
        
        return $stats;
    } catch (Exception $e) {
        logError('Error getting database stats: ' . $e->getMessage());
        return [
            'tables' => [], 
            'database_size' => 0, 
            'database_size_formatted' => '0 B',
            'total_document_size' => 0,
            'total_document_size_formatted' => '0 B'
        ];
    }
}

/**
 * Get storage statistics
 * 
 * Returns statistics about the storage
 * 
 * @return array Storage statistics
 */
function getStorageStats() {
    try {
        $stats = [];
        
        // Get uploads directory size
        $uploadsDir = BASE_PATH . 'uploads';
        $stats['uploads_size'] = getDirSize($uploadsDir);
        $stats['uploads_size_formatted'] = formatFileSize($stats['uploads_size']);
        $stats['uploads_count'] = count(glob($uploadsDir . '/*.pdf'));
        
        // Get QR codes directory size
        $qrCodesDir = BASE_PATH . 'qrcodes';
        $stats['qrcodes_size'] = getDirSize($qrCodesDir);
        $stats['qrcodes_size_formatted'] = formatFileSize($stats['qrcodes_size']);
        $stats['qrcodes_count'] = count(glob($qrCodesDir . '/*.png'));
        
        // Get logs directory size
        $logsDir = BASE_PATH . 'logs';
        $stats['logs_size'] = getDirSize($logsDir);
        $stats['logs_size_formatted'] = formatFileSize($stats['logs_size']);
        
        // Get total size
        $stats['total_size'] = $stats['uploads_size'] + $stats['qrcodes_size'] + $stats['logs_size'];
        $stats['total_size_formatted'] = formatFileSize($stats['total_size']);
        
        return $stats;
    } catch (Exception $e) {
        logError('Error getting storage stats: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get directory size
 * 
 * Returns the size of a directory in bytes
 * 
 * @param string $dir Directory path
 * @return int Directory size in bytes
 */
function getDirSize($dir) {
    $size = 0;
    
    if (!file_exists($dir) || !is_dir($dir)) {
        return $size;
    }
    
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    
    return $size;
}

/**
 * Get setting value
 * 
 * Retrieves a setting value from the database
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default if not found
 */
function getSettingValue($key, $default = null) {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        
        return $value !== false ? $value : $default;
    } catch (Exception $e) {
        logError('Error getting setting value: ' . $e->getMessage());
        return $default;
    }
}

/**
 * Update setting
 * 
 * Updates a setting in the database or creates it if it doesn't exist
 * 
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool True on success, false on failure
 */
function updateSetting($key, $value) {
    try {
        $db = getDbConnection();
        
        // Check if setting exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            // Update existing setting
            $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        } else {
            // Create new setting
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
        
        // Log the action
        logActivity('update_setting', 'setting', $key, [
            'key' => $key,
            'value' => is_scalar($value) ? $value : json_encode($value)
        ]);
        
        return true;
    } catch (Exception $e) {
        logError('Error updating setting: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get system variable value
 * 
 * Retrieves a system variable value from the database
 * 
 * @param string $key Variable key
 * @param mixed $default Default value if variable not found
 * @return mixed Variable value or default if not found
 */
function getSystemVariable($key, $default = null) {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT variable_value FROM system_variables WHERE variable_key = :key");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['variable_value'];
        }
        
        return $default;
    } catch (Exception $e) {
        error_log("Failed to get system variable: " . $e->getMessage());
        return $default;
    }
}

/**
 * Update system variable
 * 
 * Updates a system variable in the database or creates it if it doesn't exist
 * 
 * @param string $key Variable key
 * @param mixed $value Variable value
 * @param string $description Optional description
 * @return bool True on success, false on failure
 */
function updateSystemVariable($key, $value, $description = null) {
    try {
        $db = getDbConnection();
        
        // Check if variable exists
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM system_variables WHERE variable_key = :key");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            // Update existing variable
            $stmt = $db->prepare("UPDATE system_variables SET variable_value = :value, updated_at = CURRENT_TIMESTAMP WHERE variable_key = :key");
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
        } else {
            // Create new variable
            $stmt = $db->prepare("INSERT INTO system_variables (variable_key, variable_value, description) VALUES (:key, :value, :description)");
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':description', $description);
            $stmt->execute();
        }
        
        // Log the action
        logActivity('UPDATE', 'system_variable', $key, [
            'new_value' => $value
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to update system variable: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all system variables
 * 
 * Retrieves all system variables from the database
 * 
 * @return array Array of system variables
 */
function getAllSystemVariables() {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT * FROM system_variables ORDER BY variable_key");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to get system variables: " . $e->getMessage());
        return [];
    }
}

/**
 * Get system status information
 * 
 * Returns various system status information
 * 
 * @return array System status information
 */
function getSystemStatus() {
    // Include migrations file for getAvailableMigrations function
    require_once __DIR__ . '/migrations.php';
    
    $db = getDbConnection();
    $status = [];
    
    // Database information
    $status['db_version'] =  getSystemVariable('database_version', '1.0');
    $status['db_size'] = file_exists(DATABASE_FILE) ? formatFileSize(filesize(DATABASE_FILE)) : 'Unknown';
    $status['last_migration'] = $db->query("SELECT migration_name FROM migrations ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 'None';
    
    // Migration information
    $totalMigrations = count(getAvailableMigrations());
    $appliedMigrations = $db->query("SELECT COUNT(*) FROM migrations")->fetchColumn();
    $status['total_migrations'] = $totalMigrations;
    $status['applied_migrations'] = $appliedMigrations;
    $status['pending_migrations'] = $totalMigrations - $appliedMigrations;
    
    // System variables
    $status['system_variables_count'] = $db->query("SELECT COUNT(*) FROM system_variables")->fetchColumn() ?: 0;
    
    // PHP information
    $status['php_version'] = PHP_VERSION;
    $status['max_upload_size'] = formatFileSize(getMaxUploadSize());
    $status['memory_limit'] = ini_get('memory_limit');
    $status['post_max_size'] = ini_get('post_max_size');
    
    // Server information
    $status['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $status['server_protocol'] = $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown';
    
    // Application information
    $status['app_version'] = getSystemVariable('app_version', '1.0.0');
    $status['installation_date'] = getSystemVariable('system.installation_date', 'Unknown');
    
    return $status;
}

/**
 * Backup database to a file
 * 
 * Creates a backup of the database and returns the path to the backup file
 * 
 * @param string $backupDir Directory to store backup file (optional)
 * @return string|false Path to backup file or false on failure
 */
function backupDatabase($backupDir = null) {
    try {
        // Use system temp directory if no backup directory specified
        if ($backupDir === null) {
            $backupDir = sys_get_temp_dir();
        }
        
        // Ensure backup directory exists
        if (!file_exists($backupDir)) {
            if (!mkdir($backupDir, 0755, true)) {
                throw new Exception("Failed to create backup directory: $backupDir");
            }
        }
        
        // Generate backup filename with timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . DIRECTORY_SEPARATOR . "db_backup_$timestamp.sqlite";
        
        // Copy the database file
        if (!copy(DB_PATH, $backupFile)) {
            throw new Exception("Failed to copy database file to backup location");
        }
        
        // Log the backup operation
        logActivity('backup', 'database', basename(DB_PATH), [
            'backup_file' => basename($backupFile),
            'size' => filesize($backupFile)
        ]);
        
        // Update system variable for last backup time
        updateSystemVariable('system.last_backup', time(), 'Timestamp of last database backup');
        
        return $backupFile;
    } catch (Exception $e) {
        logError("Database backup failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Restore database from a backup file
 * 
 * @param string $backupFile Path to backup file
 * @return bool True if restore was successful
 */
function restoreDatabase($backupFile) {
    try {
        // Validate backup file
        if (!file_exists($backupFile)) {
            throw new Exception("Backup file does not exist: $backupFile");
        }
        
        // Create a backup before restoring (just in case)
        $currentBackup = backupDatabase();
        if (!$currentBackup) {
            throw new Exception("Failed to create safety backup before restore");
        }
        
        // Close database connection to allow file replacement
        $db = getDbConnection();
        $db = null; // Release the connection

        // Delete the current database file
        if (file_exists(DB_PATH)) {
            if (!unlink(DB_PATH)) {
                throw new Exception("Failed to delete current database file");
            }
        }

        // Create Random Database Name 
        $randomDbName = 'db_' . bin2hex(random_bytes(8)) . '.sqlite';
        define('DB_PATH_NEW', BASE_PATH . 'database' . DIRECTORY_SEPARATOR . $randomDbName);

        // Replace the current database with the backup
        if (!copy($backupFile, DB_PATH_NEW)) {
            throw new Exception("Failed to restore database from backup file");
        }
        
        // Log the restore operation
        logActivity('restore', 'database', basename(DB_PATH_NEW), [
            'backup_file' => basename($backupFile),
            'size' => filesize($backupFile)
        ]);
        
        return true;
    } catch (Exception $e) {
        logError("Database restore failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get list of available database backups
 * 
 * @param string $backupDir Directory containing backup files
 * @return array List of backup files with metadata
 */
function getAvailableBackups($backupDir) {
    $backups = [];
    
    try {
        // Ensure backup directory exists
        if (!file_exists($backupDir)) {
            return $backups;
        }
        
        // Get all .sqlite files in the backup directory
        $files = glob($backupDir . DIRECTORY_SEPARATOR . "*.sqlite");
        
        foreach ($files as $file) {
            // Extract timestamp from filename
            $filename = basename($file);
            $timestamp = null;
            
            if (preg_match('/db_backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sqlite/', $filename, $matches)) {
                $dateStr = str_replace('_', ' ', $matches[1]);
                $dateStr = preg_replace('/-/', '/', $dateStr, 3); // Replace only first 3 dashes (date part)
                $timestamp = strtotime($dateStr);
            }
            
            $backups[] = [
                'path' => $file,
                'filename' => $filename,
                'size' => filesize($file),
                'created_at' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s', filemtime($file)),
                'created' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s', filemtime($file)), // For backwards compatibility
                'created' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s', filemtime($file)), // Eski alan adı uyumluluğu için
                'timestamp' => $timestamp ?: filemtime($file)
            ];
        }
        
        // Sort backups by timestamp (newest first)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return $backups;
    } catch (Exception $e) {
        logError("Failed to get available backups: " . $e->getMessage());
        return [];
    }
}

/**
 * Get database statistics from a backup file
 * 
 * @param string $backupFile Path to the backup file
 * @return array Database statistics
 */
function getDatabaseStatsFromFile($backupFile) {
    try {
        // Create a temporary connection to the backup file
        $tempDb = new PDO('sqlite:' . $backupFile);
        $tempDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stats = [
            'tables' => [],
            'total_size' => filesize($backupFile),
            'file_modified' => filemtime($backupFile)
        ];
        
        // Get list of tables
        $tables = $tempDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        
        // Get statistics for each table
        foreach ($tables as $table) {
            // Get row count
            $rowCount = $tempDb->query("SELECT COUNT(*) FROM " . $table)->fetchColumn();
            
            // Store basic table stats
            $stats['tables'][$table] = [
                'row_count' => $rowCount,
                'size' => 0 // Will be estimated later
            ];
        }
        
        // Get total database size
        $pageCount = $tempDb->query("PRAGMA page_count")->fetchColumn();
        $pageSize = $tempDb->query("PRAGMA page_size")->fetchColumn();
        $totalSize = $pageCount * $pageSize;
        
        // Calculate total rows
        $totalRows = 0;
        foreach ($stats['tables'] as $table) {
            $totalRows += $table['row_count'];
        }
        
        // Distribute size proportionally based on row counts
        if ($totalRows > 0) {
            foreach ($stats['tables'] as $tableName => $table) {
                if ($table['row_count'] > 0) {
                    $stats['tables'][$tableName]['size'] = ($totalSize / $totalRows) * $table['row_count'];
                }
            }
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log("Failed to get database stats from file: " . $e->getMessage());
        return [
            'tables' => [],
            'total_size' => filesize($backupFile),
            'file_modified' => filemtime($backupFile),
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Recursively copy a directory
 * 
 * @param string $source Source directory
 * @param string $destination Destination directory
 * @return bool True on success, false on failure
 */
function recursiveCopy($source, $destination) {
    if (is_dir($source)) {
        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $dir = dir($source);
        while (($entry = $dir->read()) !== false) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            
            recursiveCopy($source . '/' . $entry, $destination . '/' . $entry);
        }
        
        $dir->close();
        return true;
    } else {
        return copy($source, $destination);
    }
}

/**
 * Recursively delete a directory
 * 
 * @param string $path Directory path
 * @return bool True on success, false on failure
 */
function recursiveDelete($path) {
    if (is_dir($path)) {
        $files = glob($path . '/*');
        foreach ($files as $file) {
            is_dir($file) ? recursiveDelete($file) : unlink($file);
        }
        return rmdir($path);
    } else {
        return unlink($path);
    }
}

/**
 * Download backup file from various sources
 * 
 * @param string $source Source type ('db', 's3', 'settings')
 * @param string $filename Filename to download
 * @param string $bucket S3 bucket name (optional, for S3 source)
 * @return bool|string False on failure, file path on success
 */
function downloadBackupFile($source, $filename, $bucket = null) {
    try {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'downloads';
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $downloadPath = $tempDir . DIRECTORY_SEPARATOR . basename($filename);

        switch ($source) {
            case 'db':
                // Local database backup
                $backupPath = BACKUPS_DIR . DIRECTORY_SEPARATOR . $filename;
                if (!file_exists($backupPath)) {
                    throw new Exception("Backup file not found: $filename");
                }
                if (!copy($backupPath, $downloadPath)) {
                    throw new Exception("Failed to copy backup file");
                }
                break;

            case 's3':
                // S3 backup
                if (!$bucket) {
                    throw new Exception("S3 bucket name is required");
                }

                require_once BASE_PATH . 'vendor/autoload.php';
                
                $s3Client = new Aws\S3\S3Client([
                    'version' => 'latest',
                    'region'  => getSettingValue('aws.region', 'us-east-1'),
                    'credentials' => [
                        'key'    => getSettingValue('aws.access_key'),
                        'secret' => getSettingValue('aws.secret_key')
                    ]
                ]);

                // Download from S3
                try {
                    $result = $s3Client->getObject([
                        'Bucket' => $bucket,
                        'Key'    => $filename,
                        'SaveAs' => $downloadPath
                    ]);
                } catch (Aws\S3\Exception\S3Exception $e) {
                    throw new Exception("Failed to download from S3: " . $e->getMessage());
                }
                break;

            case 'settings':
                // Settings backup
                $settingsPath = BASE_PATH . 'settings' . DIRECTORY_SEPARATOR . $filename;
                if (!file_exists($settingsPath)) {
                    throw new Exception("Settings file not found: $filename");
                }
                if (!copy($settingsPath, $downloadPath)) {
                    throw new Exception("Failed to copy settings file");
                }
                break;

            default:
                throw new Exception("Invalid source type: $source");
        }

        // Log the download
        logActivity('download_backup', $source, $filename, [
            'source' => $source,
            'filename' => $filename,
            'bucket' => $bucket
        ]);

        return $downloadPath;
    } catch (Exception $e) {
        logError("Failed to download backup: " . $e->getMessage());
        return false;
    }
}

/**
 * Stream file download to browser
 * 
 * @param string $filePath Path to file
 * @param string $filename Filename to use in download
 * @return bool True if successful, false otherwise
 */
function streamFileDownload($filePath, $filename = null) {
    try {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $filename = $filename ?: basename($filePath);
        $filesize = filesize($filePath);
        $mimetype = mime_content_type($filePath) ?: 'application/octet-stream';

        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: ' . $mimetype);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Stream file in chunks
        $handle = fopen($filePath, 'rb');
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);

        // Delete temporary file if in temp directory
        if (strpos($filePath, sys_get_temp_dir()) === 0) {
            unlink($filePath);
        }

        return true;
    } catch (Exception $e) {
        logError("Failed to stream file: " . $e->getMessage());
        return false;
    }
}

/**
 * Get backup file information
 * 
 * @param string $source Source type ('db', 's3', 'settings')
 * @param string $filename Filename
 * @param string $bucket S3 bucket name (optional)
 * @return array|false File information or false on failure
 */
function getBackupFileInfo($source, $filename, $bucket = null) {
    try {
        $info = [
            'filename' => $filename,
            'source' => $source,
            'size' => 0,
            'modified' => null,
            'type' => null
        ];

        switch ($source) {
            case 'db':
                $path = BACKUPS_DIR . DIRECTORY_SEPARATOR . $filename;
                if (!file_exists($path)) {
                    throw new Exception("File not found");
                }
                $info['size'] = filesize($path);
                $info['modified'] = filemtime($path);
                $info['type'] = 'database';
                break;

            case 's3':
                if (!$bucket) {
                    throw new Exception("S3 bucket name is required");
                }

                require_once BASE_PATH . 'vendor/autoload.php';
                
                $s3Client = new Aws\S3\S3Client([
                    'version' => 'latest',
                    'region'  => getSettingValue('aws.region', 'us-east-1'),
                    'credentials' => [
                        'key'    => getSettingValue('aws.access_key'),
                        'secret' => getSettingValue('aws.secret_key')
                    ]
                ]);

                try {
                    $result = $s3Client->headObject([
                        'Bucket' => $bucket,
                        'Key'    => $filename
                    ]);
                    
                    $info['size'] = $result['ContentLength'];
                    $info['modified'] = $result['LastModified']->getTimestamp();
                    $info['type'] = 'cloud';
                } catch (Aws\S3\Exception\S3Exception $e) {
                    throw new Exception("Failed to get S3 file info: " . $e->getMessage());
                }
                break;

            case 'settings':
                $path = BASE_PATH . 'settings' . DIRECTORY_SEPARATOR . $filename;
                if (!file_exists($path)) {
                    throw new Exception("File not found");
                }
                $info['size'] = filesize($path);
                $info['modified'] = filemtime($path);
                $info['type'] = 'settings';
                break;

            default:
                throw new Exception("Invalid source type");
        }

        $info['size_formatted'] = formatFileSize($info['size']);
        $info['modified_formatted'] = date('Y-m-d H:i:s', $info['modified']);

        return $info;
    } catch (Exception $e) {
        logError("Failed to get backup file info: " . $e->getMessage());
        return false;
    }
}

?>
