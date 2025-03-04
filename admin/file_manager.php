<?php
/**
 * File Manager
 * 
 * Manage PDF files in the uploads directory that are not in the database
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Initialize variables
$message = '';
$messageType = '';
$orphanedFiles = [];
$totalSize = 0;
$uploadsDir = BASE_PATH . 'uploads';
$viewFile = null;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Get database connection
$db = getDbConnection();

// Get all PDF files in uploads directory
$pdfFiles = glob($uploadsDir . '/*.pdf');

// Get all PDF files registered in the database
$registeredFiles = [];
$stmt = $db->query("SELECT filename, original_filename FROM documents");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($row['filename'])) {
        // Extract filename from path
        $registeredFiles[$row['filename']] = true;
    }
    if (!empty($row['original_filename'])) {
        // Also check original filename
        $registeredFiles[$row['original_filename']] = true;
    }
}

// Find orphaned files (files in uploads directory but not in database)
foreach ($pdfFiles as $pdfFile) {
    $filename = basename($pdfFile);
    if (!isset($registeredFiles[$filename])) {
        $fileInfo = [
            'name' => $filename,
            'path' => $pdfFile,
            'size' => filesize($pdfFile),
            'size_formatted' => formatFileSize(filesize($pdfFile)),
            'modified' => filemtime($pdfFile),
            'modified_formatted' => date('Y-m-d H:i:s', filemtime($pdfFile))
        ];
        $orphanedFiles[] = $fileInfo;
        $totalSize += $fileInfo['size'];
    }
}

// Process view file request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['view'])) {
    $filename = $_GET['view'];
    $filePath = $uploadsDir . '/' . basename($filename);
    
    if (file_exists($filePath) && is_file($filePath)) {
        // Set the file to view
        $viewFile = [
            'name' => basename($filePath),
            'path' => $filePath,
            'size' => filesize($filePath),
            'size_formatted' => formatFileSize(filesize($filePath)),
            'modified' => filemtime($filePath),
            'modified_formatted' => date('Y-m-d H:i:s', filemtime($filePath))
        ];
    } else {
        $message = 'File not found.';
        $messageType = 'error';
    }
}

// Process delete all orphaned files request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_files'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'error';
    } else {
        $deletedCount = 0;
        $totalSize = 0;
        $failedCount = 0;
        
        // Get all orphaned files
        $orphanedFiles = [];
        $pdfFiles = glob($uploadsDir . '/*.pdf');
        
        // Get all PDF files registered in the database
        $registeredFiles = [];
        $stmt = $db->query("SELECT filename, original_filename FROM documents");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['filename'])) {
                $registeredFiles[$row['filename']] = true;
            }
            if (!empty($row['original_filename'])) {
                $registeredFiles[$row['original_filename']] = true;
            }
        }
        
        // Find and delete orphaned files
        foreach ($pdfFiles as $pdfFile) {
            $filename = basename($pdfFile);
            if (!isset($registeredFiles[$filename])) {
                if (file_exists($pdfFile) && is_file($pdfFile)) {
                    $fileSize = filesize($pdfFile);
                    if (unlink($pdfFile)) {
                        $deletedCount++;
                        $totalSize += $fileSize;
                        
                        // Log activity
                        logActivity('DELETE', 'file', $filename, [
                            'size' => $fileSize,
                            'batch_delete' => true
                        ]);
                    } else {
                        $failedCount++;
                    }
                }
            }
        }
        
        if ($deletedCount > 0) {
            $message = "Successfully deleted $deletedCount orphaned PDF files (Total: " . formatFileSize($totalSize) . ").";
            if ($failedCount > 0) {
                $message .= " Failed to delete $failedCount files.";
            }
            $messageType = 'success';
        } else {
            $message = 'No orphaned PDF files were found to delete.';
            $messageType = 'info';
        }
        
        // Refresh orphaned files list
        $orphanedFiles = [];
        $totalSize = 0;
        $pdfFiles = glob($uploadsDir . '/*.pdf');
        foreach ($pdfFiles as $pdfFile) {
            $filename = basename($pdfFile);
            if (!isset($registeredFiles[$filename])) {
                $fileInfo = [
                    'name' => $filename,
                    'path' => $pdfFile,
                    'size' => filesize($pdfFile),
                    'size_formatted' => formatFileSize(filesize($pdfFile)),
                    'modified' => filemtime($pdfFile),
                    'modified_formatted' => date('Y-m-d H:i:s', filemtime($pdfFile))
                ];
                $orphanedFiles[] = $fileInfo;
                $totalSize += $fileInfo['size'];
            }
        }
    }
}

// Process import all files request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_all_files'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'error';
    } else {
        $importedCount = 0;
        $failedCount = 0;
        
        // Get all orphaned files
        $pdfFiles = glob($uploadsDir . '/*.pdf');
        
        // Get all PDF files registered in the database
        $registeredFiles = [];
        $stmt = $db->query("SELECT filename, original_filename FROM documents");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['filename'])) {
                $registeredFiles[$row['filename']] = true;
            }
            if (!empty($row['original_filename'])) {
                $registeredFiles[$row['original_filename']] = true;
            }
        }
        
        // Find and import orphaned files
        foreach ($pdfFiles as $pdfFile) {
            $filename = basename($pdfFile);
            if (!isset($registeredFiles[$filename])) {
                if (file_exists($pdfFile) && is_file($pdfFile)) {
                    // Generate UUID for document
                    $uuid = generateUUID();
                    
                    // Get file metadata
                    $fileSize = filesize($pdfFile);
                    $fileModified = filemtime($pdfFile);
                    
                    try {
                        // Start transaction
                        $db->beginTransaction();
                        
                        // Get current user ID
                        $user_id = $_SESSION['user_id'] ?? 1; // Default to admin if not set
                        
                        // Generate short URL
                        $short_url = generateShortUrl();
                        
                        // Generate QR code
                        $qrCodeUrl = BASE_URL . 'view.php?id=' . $short_url;
                        $qrCode = generateQRCode($qrCodeUrl);
                        
                        // Insert document into database
                        $stmt = $db->prepare("INSERT INTO documents (uuid, title, description, filename, original_filename, file_size, mime_type, short_url, qr_code, user_id, is_public, created_at, updated_at) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, datetime('now'), datetime('now'))");
                        $stmt->execute([
                            $uuid,
                            'Imported: ' . basename($pdfFile),
                            'Automatically imported from orphaned files',
                            basename($pdfFile),
                            basename($pdfFile),
                            $fileSize,
                            'application/pdf',
                            $short_url,
                            $qrCode,
                            $user_id
                        ]);
                        
                        // Commit transaction
                        $db->commit();
                        
                        $importedCount++;
                        
                        // Log activity
                        logActivity('IMPORT', 'document', $uuid, [
                            'file_path' => basename($pdfFile),
                            'file_size' => $fileSize
                        ]);
                    } catch (Exception $e) {
                        // Rollback transaction
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        
                        $failedCount++;
                        
                        // Log error
                        error_log('Error importing file: ' . $e->getMessage());
                    }
                }
            }
        }
        
        if ($importedCount > 0) {
            $message = "Successfully imported $importedCount orphaned PDF files.";
            if ($failedCount > 0) {
                $message .= " Failed to import $failedCount files.";
            }
            $messageType = 'success';
        } else {
            $message = 'No orphaned PDF files were found to import.';
            $messageType = 'info';
        }
        
        // Refresh orphaned files list
        $orphanedFiles = [];
        $totalSize = 0;
        $pdfFiles = glob($uploadsDir . '/*.pdf');
        foreach ($pdfFiles as $pdfFile) {
            $filename = basename($pdfFile);
            if (!isset($registeredFiles[$filename])) {
                $fileInfo = [
                    'name' => $filename,
                    'path' => $pdfFile,
                    'size' => filesize($pdfFile),
                    'size_formatted' => formatFileSize(filesize($pdfFile)),
                    'modified' => filemtime($pdfFile),
                    'modified_formatted' => date('Y-m-d H:i:s', filemtime($pdfFile))
                ];
                $orphanedFiles[] = $fileInfo;
                $totalSize += $fileInfo['size'];
            }
        }
    }
}

// Process delete file request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'error';
    } else if (!isset($_POST['filename']) || empty($_POST['filename'])) {
        $message = 'No file selected.';
        $messageType = 'error';
    } else {
        $filename = $_POST['filename'];
        $filePath = $uploadsDir . '/' . basename($filename);
        
        if (file_exists($filePath) && is_file($filePath)) {
            $file_size = filesize($filePath);
            if (unlink($filePath)) {
                $message = 'File deleted successfully: ' . basename($filePath);
                $messageType = 'success';
                
                // Log activity
                logActivity('DELETE', 'file', basename($filePath), [
                    'size' => $file_size
                ]);
                
                // Refresh orphaned files list
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $message = 'Failed to delete file.';
                $messageType = 'error';
                
                // Log activity
                logActivity('DELETE', 'file', basename($filePath), [
                    'size' => $file_size
                ]);
            }
        } else {
            $message = 'File not found.';
            $messageType = 'error';
            
            // Log activity
            logActivity('DELETE', 'file', basename($filePath), [
                'size' => $file_size
            ]);
        }
    }

    // Refresh orphaned files list
    $orphanedFiles = [];
    $totalSize = 0;
    $pdfFiles = glob($uploadsDir . '/*.pdf');
    foreach ($pdfFiles as $pdfFile) {
        $filename = basename($pdfFile);
        if (!isset($registeredFiles[$filename])) {
            $fileInfo = [
                'name' => $filename,
                'path' => $pdfFile,
                'size' => filesize($pdfFile),
                'size_formatted' => formatFileSize(filesize($pdfFile)),
                'modified' => filemtime($pdfFile),
                'modified_formatted' => date('Y-m-d H:i:s', filemtime($pdfFile))
            ];
            $orphanedFiles[] = $fileInfo;
            $totalSize += $fileInfo['size'];
        }
    }
}

// Process import file request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_file'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'error';
    } else if (!isset($_POST['filename']) || empty($_POST['filename'])) {
        $message = 'No file selected.';
        $messageType = 'error';
    } else {
        $filename = $_POST['filename'];
        $filePath = $uploadsDir . '/' . basename($filename);
        
        if (file_exists($filePath) && is_file($filePath)) {
            // Generate UUID for document
            $uuid = generateUUID();
            
            // Get file metadata
            $fileSize = filesize($filePath);
            $fileModified = filemtime($filePath);
            
            try {
                // Start transaction
                $db->beginTransaction();
                
                // Get current user ID
                $user_id = $_SESSION['user_id'] ?? 1; // Default to admin if not set
                
                // Generate short URL
                $short_url = generateShortUrl();
                
                // Generate QR code
                $qrCodeUrl = BASE_URL . 'view.php?id=' . $short_url;
                $qrCode = generateQRCode($qrCodeUrl);
                
                // Insert document into database
                $stmt = $db->prepare("INSERT INTO documents (uuid, title, description, filename, original_filename, file_size, mime_type, short_url, qr_code, user_id, is_public, created_at, updated_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, datetime('now'), datetime('now'))");
                $stmt->execute([
                    $uuid,
                    'Imported: ' . basename($filePath),
                    'Automatically imported from orphaned files',
                    basename($filePath),
                    basename($filePath),
                    $fileSize,
                    'application/pdf',
                    $short_url,
                    $qrCode,
                    $user_id
                ]);
                
                // Commit transaction
                $db->commit();
                
                $message = 'File imported successfully: ' . basename($filePath);
                $messageType = 'success';
                
                // Log activity
                logActivity('IMPORT', 'document', $uuid, [
                    'file_path' => basename($filePath),
                    'file_size' => $fileSize
                ]);
                
                // Refresh orphaned files list
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } catch (Exception $e) {
                // Rollback transaction
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                
                $message = 'Failed to import file: ' . $e->getMessage();
                $messageType = 'error';
                
                // Log error
                error_log('Error importing file: ' . $e->getMessage());
            }
        } else {
            $message = 'File not found.';
            $messageType = 'error';
        }
    }

    // Refresh orphaned files list
    $orphanedFiles = [];
    $totalSize = 0;
    $pdfFiles = glob($uploadsDir . '/*.pdf');
    foreach ($pdfFiles as $pdfFile) {
        $filename = basename($pdfFile);
        if (!isset($registeredFiles[$filename])) {
            $fileInfo = [
                'name' => $filename,
                'path' => $pdfFile,
                'size' => filesize($pdfFile),
                'size_formatted' => formatFileSize(filesize($pdfFile)),
                'modified' => filemtime($pdfFile),
                'modified_formatted' => date('Y-m-d H:i:s', filemtime($pdfFile))
            ];
            $orphanedFiles[] = $fileInfo;
            $totalSize += $fileInfo['size'];
        }
    }
}

// Include header
$pageTitle = 'File Manager';
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">PDF File Manager</h1>
    
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 <?php echo $viewFile ? 'lg:grid-cols-2 gap-4' : ''; ?>">
        <?php if ($viewFile): ?>
            <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <h2 class="text-xl font-bold mb-4">Viewing File: <?php echo htmlspecialchars($viewFile['name']); ?></h2>
                <div class="flex justify-between items-center mb-4">
                    <p class="text-gray-600">
                        File size: <?php echo $viewFile['size_formatted']; ?>, Modified: <?php echo $viewFile['modified_formatted']; ?>
                    </p>
                    <div class="flex space-x-2">
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($viewFile['name']); ?>">
                            <button type="submit" name="import_file" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-3 rounded text-sm focus:outline-none focus:shadow-outline">
                                <i class="bi bi-plus-circle"></i> Import
                            </button>
                        </form>
                        
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline" onsubmit="return confirm('Are you sure you want to delete this file?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($viewFile['name']); ?>">
                            <button type="submit" name="delete_file" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-sm focus:outline-none focus:shadow-outline">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </form>
                        
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-1 px-3 rounded text-sm focus:outline-none focus:shadow-outline">
                            <i class="bi bi-x"></i> Close
                        </a>
                    </div>
                </div>
                <embed src="<?php echo BASE_URL . 'uploads/' . urlencode($viewFile['name']); ?>" type="application/pdf" width="100%" height="600">
            </div>
        <?php endif; ?>
        
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-xl font-bold mb-4">Orphaned PDF Files</h2>
            <p class="mb-4 text-gray-600">
                These PDF files exist in the uploads directory but are not registered in the database.
                You can view, import, or delete these files.
            </p>
            
            <?php if (count($orphanedFiles) > 0): ?>
                <div class="mb-4">
                    <p><strong>Total Files:</strong> <?php echo count($orphanedFiles); ?></p>
                    <p><strong>Total Size:</strong> <?php echo formatFileSize($totalSize); ?></p>
                </div>
                
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline" onsubmit="return confirm('Are you sure you want to delete all <?php echo count($orphanedFiles); ?> orphaned files? This action cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <button type="submit" name="delete_all_files" class="bg-red-600 hover:bg-red-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                <i class="bi bi-trash"></i> Delete All Orphaned Files
                            </button>
                        </form>
                    </div>
                    <div>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <button type="submit" name="import_all_files" class="bg-green-600 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                <i class="bi bi-plus-circle"></i> Import All Files
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 border-b text-left">Filename</th>
                                <th class="py-2 px-4 border-b text-left">Size</th>
                                <th class="py-2 px-4 border-b text-left">Modified</th>
                                <th class="py-2 px-4 border-b text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orphanedFiles as $file): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($file['name']); ?></td>
                                    <td class="py-2 px-4 border-b"><?php echo $file['size_formatted']; ?></td>
                                    <td class="py-2 px-4 border-b"><?php echo $file['modified_formatted']; ?></td>
                                    <td class="py-2 px-4 border-b">
                                        <div class="flex space-x-2">
                                            <a href="?view=<?php echo urlencode($file['name']); ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            
                                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                <button type="submit" name="import_file" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline">
                                                    <i class="bi bi-plus-circle"></i> Import
                                                </button>
                                            </form>
                                            
                                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                <button type="submit" name="delete_file" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs focus:outline-none focus:shadow-outline">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
                    <p>No orphaned PDF files found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="<?php echo BASE_URL; ?>admin/index.php" class="text-blue-500 hover:text-blue-700">
            <i class="bi bi-arrow-left mr-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php include_once 'footer.php'; ?>
