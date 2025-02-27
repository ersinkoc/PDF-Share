<?php
/**
 * Backup and Restore Page
 * 
 * Allows administrators to backup and restore the database and files
 */

// Include initialization file
require_once '../includes/init.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get database statistics
$dbStats = getDatabaseStats();

// Initialize variables
$error = '';
$success = '';
$backupData = null;
$restoreData = null;

// Generate CSRF token if not exists
if (!isset($_SESSION['backup_csrf_token'])) {
    $_SESSION['backup_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['backup_csrf_token'];

// Process document backup request (database + files)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_document_backup'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['backup_csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        try {
            // Create temporary directory for backup files
            $tempDir = sys_get_temp_dir() . '/pdf_qr_backup_' . time();
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }
            
            // Create directories for files
            mkdir($tempDir . '/pdfs', 0777, true);
            mkdir($tempDir . '/qrcodes', 0777, true);
            
            // Get database connection
            $db = getDbConnection();
            
            // Create backup data structure - only for documents table
            $backupData = [
                'metadata' => [
                    'version' => '1.0',
                    'timestamp' => time(),
                    'date' => date('Y-m-d H:i:s'),
                    'app' => 'PDF QR Link',
                    'type' => 'document_backup'
                ],
                'documents' => []
            ];
            
            // Get all documents
            $stmt = $db->prepare("SELECT * FROM documents");
            $stmt->execute();
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $backupData['documents'] = $documents;
            
            // Save document data as JSON
            file_put_contents($tempDir . '/documents.json', json_encode($backupData, JSON_PRETTY_PRINT));
            
            // Copy PDF files and QR codes
            $pdfCount = 0;
            $qrCount = 0;
            
            foreach ($documents as $document) {
                // Copy PDF file
                $pdfPath = BASE_PATH . 'uploads/' . $document['filename'];
                if (file_exists($pdfPath)) {
                    copy($pdfPath, $tempDir . '/pdfs/' . $document['filename']);
                    $pdfCount++;
                }
                
                // Copy QR code image
                $qrPath = BASE_PATH . $document['qr_code'];
                if (file_exists($qrPath)) {
                    $qrFilename = basename($document['qr_code']);
                    copy($qrPath, $tempDir . '/qrcodes/' . $qrFilename);
                    $qrCount++;
                }
            }
            
            // Create ZIP archive
            $zipFilename = 'pdf_qr_backup_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = sys_get_temp_dir() . '/' . $zipFilename;
            
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                throw new Exception("Cannot create ZIP archive");
            }
            
            // Add files to ZIP
            addDirToZip($zip, $tempDir, '');
            $zip->close();
            
            // Clean up temporary directory
            rrmdir($tempDir);
            
            // Log the backup activity
            logActivity('backup', 'documents', 0, [
                'type' => 'document_backup',
                'pdf_count' => $pdfCount,
                'qr_count' => $qrCount
            ]);
            
            // Update last backup time
            updateSystemVariable('system.last_backup', date('Y-m-d H:i:s'));
            
            // Set success message
            $success = 'Document backup created successfully. <a href="download_import_export.php?file=' . urlencode($zipFilename) . '&csrf_token=' . $csrf_token . '" class="text-blue-500 underline">Download Backup</a>';
            
            // Send ZIP file to browser
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            
            // Delete temporary ZIP file
            unlink($zipPath);
            exit;
        } catch (Exception $e) {
            $error = 'Error creating document backup: ' . $e->getMessage();
        }
    }
}

// Process document restore request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_document_backup'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['backup_csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        // Check if file was uploaded
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a valid backup file.';
        } else {
            try {
                // Create temporary directory for extracted files
                $tempDir = sys_get_temp_dir() . '/pdf_qr_restore_' . time();
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0777, true);
                }
                
                // Extract ZIP file
                $zip = new ZipArchive();
                if ($zip->open($_FILES['backup_file']['tmp_name']) !== true) {
                    throw new Exception("Cannot open ZIP archive");
                }
                
                $zip->extractTo($tempDir);
                $zip->close();
                
                // Check if documents.json exists
                if (!file_exists($tempDir . '/documents.json')) {
                    throw new Exception("Invalid backup: documents.json not found");
                }
                
                // Read document backup
                $jsonData = file_get_contents($tempDir . '/documents.json');
                $backupData = json_decode($jsonData, true);
                
                // Validate backup data
                if (!isset($backupData['metadata']) || !isset($backupData['documents'])) {
                    throw new Exception("Invalid backup file format");
                }
                
                // Get database connection
                $db = getDbConnection();
                
                // Begin transaction
                $db->beginTransaction();
                
                try {
                    // Temporarily disable foreign key constraints
                    $db->exec('PRAGMA foreign_keys = OFF');
                    
                    // Process documents
                    $restoredCount = 0;
                    $skippedCount = 0;
                    $errorCount = 0;
                    
                    foreach ($backupData['documents'] as $document) {
                        try {
                            // Check if document with this UUID already exists
                            $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE uuid = :uuid");
                            $stmt->bindParam(':uuid', $document['uuid']);
                            $stmt->execute();
                            
                            if ($stmt->fetchColumn() > 0) {
                                // Document already exists, skip
                                $skippedCount++;
                                continue;
                            }
                            
                            // Check if user exists, if not create a reference to admin user
                            if (isset($document['user_uuid'])) {
                                $userStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE uuid = :uuid");
                                $userStmt->bindParam(':uuid', $document['user_uuid']);
                                $userStmt->execute();
                                
                                if ($userStmt->fetchColumn() == 0) {
                                    // User doesn't exist, get admin user
                                    $adminStmt = $db->prepare("SELECT id, uuid FROM users WHERE is_admin = 1 LIMIT 1");
                                    $adminStmt->execute();
                                    $adminUser = $adminStmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($adminUser) {
                                        // Replace user_id and user_uuid with admin user
                                        $document['user_id'] = $adminUser['id'];
                                        $document['user_uuid'] = $adminUser['uuid'];
                                    }
                                }
                            }
                            
                            // Filter out any columns that don't exist in the table
                            $tableInfoStmt = $db->prepare("PRAGMA table_info(documents)");
                            $tableInfoStmt->execute();
                            $tableColumns = array_map(function($col) {
                                return $col['name'];
                            }, $tableInfoStmt->fetchAll(PDO::FETCH_ASSOC));
                            
                            $filteredDocument = array_intersect_key($document, array_flip($tableColumns));
                            
                            // Insert document
                            $columns = array_keys($filteredDocument);
                            $placeholders = array_fill(0, count($columns), '?');
                            
                            $sql = "INSERT INTO documents (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                            $stmt = $db->prepare($sql);
                            
                            $i = 1;
                            foreach ($filteredDocument as $value) {
                                $stmt->bindValue($i++, $value);
                            }
                            
                            $stmt->execute();
                            $restoredCount++;
                            
                            // Copy PDF file if exists
                            $pdfSourcePath = $tempDir . '/pdfs/' . $document['filename'];
                            $pdfDestPath = BASE_PATH . 'uploads/' . $document['filename'];
                            
                            if (file_exists($pdfSourcePath) && !file_exists($pdfDestPath)) {
                                copy($pdfSourcePath, $pdfDestPath);
                            }
                            
                            // Copy QR code image if exists
                            $qrFilename = basename($document['qr_code']);
                            $qrSourcePath = $tempDir . '/qrcodes/' . $qrFilename;
                            $qrDestPath = BASE_PATH . 'cache/' . $qrFilename;
                            
                            // Create directory if it doesn't exist
                            $qrDir = dirname($qrDestPath);
                            if (!file_exists($qrDir)) {
                                mkdir($qrDir, 0777, true);
                            }
                            
                            if (file_exists($qrSourcePath) && !file_exists($qrDestPath)) {
                                copy($qrSourcePath, $qrDestPath);
                            }
                        } catch (Exception $docException) {
                            // Log individual document error but continue with others
                            error_log("Error restoring document {$document['uuid']}: " . $docException->getMessage());
                            $errorCount++;
                        }
                    }
                    
                    // Re-enable foreign key constraints
                    $db->exec('PRAGMA foreign_keys = ON');
                    
                    // Commit transaction
                    $db->commit();
                    
                    // Clean up temporary directory
                    rrmdir($tempDir);
                    
                    // Log the restore activity
                    logActivity('restore', 'documents', 0, [
                        'type' => 'document_backup',
                        'restored_count' => $restoredCount,
                        'skipped_count' => $skippedCount,
                        'error_count' => $errorCount
                    ]);
                    
                    $success = "Documents restored successfully. Restored: $restoredCount, Skipped (already exist): $skippedCount";
                    if ($errorCount > 0) {
                        $success .= ", Errors: $errorCount (see error log for details)";
                    }
                } catch (Exception $e) {
                    // Rollback transaction
                    $db->rollBack();
                    $error = 'Error restoring documents: ' . $e->getMessage();
                }
            } catch (Exception $e) {
                $error = 'Error processing backup file: ' . $e->getMessage();
            }
        }
    }
}

/**
 * Get table schema
 * 
 * @param PDO $db Database connection
 * @param string $table Table name
 * @return string Table schema SQL
 */
function getTableSchema($db, $table) {
    $result = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetch(PDO::FETCH_ASSOC);
    return $result['sql'];
}

/**
 * Add directory contents to ZIP archive
 * 
 * @param ZipArchive $zip ZIP archive
 * @param string $dir Directory to add
 * @param string $zipDir Directory in ZIP archive
 */
function addDirToZip($zip, $dir, $zipDir) {
    $dir = rtrim($dir, '/\\') . '/';
    
    if (!is_dir($dir)) {
        return;
    }
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $filePath = $dir . $file;
        $zipPath = $zipDir . ($zipDir !== '' ? '/' : '') . $file;
        
        if (is_dir($filePath)) {
            $zip->addEmptyDir($zipPath);
            addDirToZip($zip, $filePath, $zipPath);
        } else {
            $zip->addFile($filePath, $zipPath);
        }
    }
}

/**
 * Recursively remove directory
 * 
 * @param string $dir Directory to remove
 */
function rrmdir($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            unlink($path);
        }
    }
    
    rmdir($dir);
}

// Include header
$pageTitle = 'Import & Export';
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Import & Export</h1>
    
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Backup Section -->
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-xl font-bold mb-4">Export Data</h2>
            <p class="mb-4 text-gray-600">
                Export your document data to a ZIP file. This will include document data, PDF files, and QR codes.
            </p>
            
            <div class="border rounded p-4 bg-blue-50">
                <h3 class="font-bold mb-2">Export Data</h3>
                <p class="text-sm text-gray-600 mb-4">
                    This backup can be used to import documents to another system.
                </p>
                
                <form action="import_export.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <button type="submit" name="create_document_backup" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                        <i class="bi bi-archive mr-2"></i> Export Now (ZIP)
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Import Section -->
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-xl font-bold mb-4">Import from Backup</h2>
            <p class="mb-4 text-gray-600">
                Import your documents from a previously created backup file. Only documents with new UUIDs will be added.
            </p>
            
            <div class="border rounded p-4 bg-yellow-50">
                <h3 class="font-bold mb-2">Document Import</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Documents with UUIDs that already exist in the system will be skipped.
                </p>
                
                <form action="import_export.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="mb-4">
                        <label for="backup_file" class="block text-gray-700 text-sm font-bold mb-2">Backup File (ZIP)</label>
                        <input type="file" id="backup_file" name="backup_file" accept="application/zip" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    
                    <button type="submit" name="restore_document_backup" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full" onclick="return confirm('Are you sure you want to restore documents? Existing documents with the same UUID will be skipped.')">
                        <i class="bi bi-archive-fill mr-2"></i> Import Now (ZIP)
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4 mt-6">
        <h2 class="text-xl font-bold mb-4">Database Statistics</h2>
        <p class="mb-4 text-gray-600">
            Detailed information about your database and storage.
        </p>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h3 class="text-lg font-semibold text-blue-700 mb-2">Database</h3>
                <div class="text-sm">
                    <p class="flex justify-between py-1 border-b border-blue-100">
                        <span>Size:</span>
                        <span class="font-medium"><?php echo $dbStats['database_size_formatted']; ?></span>
                    </p>
                    <p class="flex justify-between py-1 border-b border-blue-100">
                        <span>Total Tables:</span>
                        <span class="font-medium"><?php echo count($dbStats['tables']); ?></span>
                    </p>
                    <p class="flex justify-between py-1">
                        <span>Last Backup:</span>
                        <span class="font-medium">
                            <?php 
                            $lastBackup = getSystemVariable('system.last_backup', null);
                            echo $lastBackup ? $lastBackup : 'Never';
                            ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <h3 class="text-lg font-semibold text-green-700 mb-2">Documents</h3>
                <div class="text-sm">
                    <p class="flex justify-between py-1 border-b border-green-100">
                        <span>Total Documents:</span>
                        <span class="font-medium"><?php echo isset($dbStats['tables']['documents']) ? $dbStats['tables']['documents']['row_count'] : 0; ?></span>
                    </p>
                    <p class="flex justify-between py-1 border-b border-green-100">
                        <span>Total Size:</span>
                        <span class="font-medium"><?php echo $dbStats['total_document_size_formatted'] ?? '0 B'; ?></span>
                    </p>
                    <p class="flex justify-between py-1">
                        <span>Total Views:</span>
                        <span class="font-medium"><?php echo isset($dbStats['tables']['views']) ? $dbStats['tables']['views']['row_count'] : 0; ?></span>
                    </p>
                </div>
            </div>
            
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <h3 class="text-lg font-semibold text-purple-700 mb-2">Storage</h3>
                <div class="text-sm">
                    <p class="flex justify-between py-1 border-b border-purple-100">
                        <span>PDF Files:</span>
                        <span class="font-medium"><?php echo $dbStats['storage']['uploads_count']; ?> (<?php echo $dbStats['storage']['uploads_size_formatted']; ?>)</span>
                    </p>
                    <p class="flex justify-between py-1 border-b border-purple-100">
                        <span>QR Codes:</span>
                        <span class="font-medium"><?php echo $dbStats['storage']['qrcodes_count']; ?> (<?php echo $dbStats['storage']['qrcodes_size_formatted']; ?>)</span>
                    </p>
                    <p class="flex justify-between py-1">
                        <span>Logs:</span>
                        <span class="font-medium"><?php echo $dbStats['storage']['logs_size_formatted']; ?></span>
                    </p>
                </div>
            </div>
        </div>
        
        <h3 class="text-lg font-semibold mb-3">Table Details</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-2 px-4 border-b text-left">Table Name</th>
                        <th class="py-2 px-4 border-b text-left">Rows</th>
                        <th class="py-2 px-4 border-b text-left">Columns</th>
                        <th class="py-2 px-4 border-b text-left">Indexes</th>
                        <th class="py-2 px-4 border-b text-left">Last Record</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dbStats['tables'] as $tableName => $tableStats): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($tableName); ?></td>
                        <td class="py-2 px-4 border-b"><?php echo $tableStats['row_count']; ?></td>
                        <td class="py-2 px-4 border-b"><?php echo $tableStats['column_count']; ?></td>
                        <td class="py-2 px-4 border-b"><?php echo $tableStats['index_count']; ?></td>
                        <td class="py-2 px-4 border-b">
                            <?php 
                            if (isset($tableStats['row_count']) && $tableStats['row_count'] > 0) {
                                try {
                                    $db = getDbConnection();
                                    // Check if created_at column exists in this table
                                    $stmt = $db->prepare("PRAGMA table_info(\"$tableName\")");
                                    $stmt->execute();
                                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    $hasCreatedAt = false;
                                    
                                    foreach ($columns as $column) {
                                        if ($column['name'] === 'created_at') {
                                            $hasCreatedAt = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($hasCreatedAt) {
                                        $stmt = $db->prepare("SELECT created_at FROM \"$tableName\" ORDER BY created_at DESC LIMIT 1");
                                        $stmt->execute();
                                        $lastDate = $stmt->fetchColumn();
                                        echo $lastDate ? date('Y-m-d H:i:s', strtotime($lastDate)) : 'N/A';
                                    } else {
                                        echo 'No timestamp';
                                    }
                                } catch (Exception $e) {
                                    echo 'N/A';
                                }
                            } else {
                                echo 'No records';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-3">Storage Usage</h3>
            <div class="h-6 bg-gray-200 rounded-full overflow-hidden">
                <?php
                $totalSpace = disk_total_space(BASE_PATH);
                $freeSpace = disk_free_space(BASE_PATH);
                $usedSpace = $totalSpace - $freeSpace;
                $usedPercent = round(($usedSpace / $totalSpace) * 100, 2);
                $appPercent = round(($dbStats['storage']['total_size'] / $totalSpace) * 100, 2);
                ?>
                <div class="h-full bg-blue-500" style="width: <?php echo $usedPercent; ?>%"></div>
            </div>
            <div class="flex justify-between text-sm mt-1">
                <span>Used: <?php echo formatFileSize($usedSpace); ?> (<?php echo $usedPercent; ?>%)</span>
                <span>Free: <?php echo formatFileSize($freeSpace); ?></span>
                <span>Total: <?php echo formatFileSize($totalSpace); ?></span>
            </div>
            <p class="text-sm text-gray-600 mt-2">
                App Storage: <?php echo $dbStats['storage']['total_size_formatted']; ?> (<?php echo $appPercent; ?>% of total disk space)
            </p>
        </div>
    </div>
    

</div>

<?php include 'footer.php'; ?>
