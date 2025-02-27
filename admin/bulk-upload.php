<?php
/**
 * Bulk Upload Documents Page
 * 
 * Allows administrators to upload multiple PDF documents at once
 */

// Include initialization file
require_once '../includes/init.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Initialize variables
$isPublic = 0;
$error = '';
$success = '';
$uploadedFiles = [];
$failedFiles = [];

// Check if this is an AJAX request
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_upload'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please try again.';
        
        if ($isAjaxRequest) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    } else {
        // Get form data
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        // Check if files were uploaded
        if (!isset($_FILES['pdf_files']) || empty($_FILES['pdf_files']['name'][0])) {
            $error = 'Please select at least one PDF file to upload.';
            
            if ($isAjaxRequest) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }
        } else {
            // Get total storage usage
            $storageUsage = getTotalStorageUsage();
            $totalFileSize = 0;
            
            // Count total size of all files
            foreach ($_FILES['pdf_files']['size'] as $size) {
                $totalFileSize += $size;
            }
            
            // Check if there's enough storage space
            if ($storageUsage['total_size'] + $totalFileSize > MAX_STORAGE_SIZE) {
                $error = 'Storage limit exceeded. Maximum storage size is ' . formatBytes(MAX_STORAGE_SIZE) . '.';
                
                if ($isAjaxRequest) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $error]);
                    exit;
                }
            } else {
                // Create upload directory if it doesn't exist
                if (!file_exists('../uploads')) {
                    mkdir('../uploads', 0755, true);
                }
                
                // Get database connection
                $db = getDbConnection();
                
                // Process each file
                $fileCount = count($_FILES['pdf_files']['name']);
                $maxFilesToProcess = min($fileCount, 20); // Limit to 20 files
                
                for ($i = 0; $i < $maxFilesToProcess; $i++) {
                    $currentFile = [
                        'name' => $_FILES['pdf_files']['name'][$i],
                        'type' => $_FILES['pdf_files']['type'][$i],
                        'tmp_name' => $_FILES['pdf_files']['tmp_name'][$i],
                        'error' => $_FILES['pdf_files']['error'][$i],
                        'size' => $_FILES['pdf_files']['size'][$i]
                    ];
                    
                    // Skip empty entries
                    if (empty($currentFile['name'])) {
                        continue;
                    }
                    
                    // Check for upload errors
                    if ($currentFile['error'] !== UPLOAD_ERR_OK) {
                        $failedFiles[] = [
                            'name' => $currentFile['name'],
                            'reason' => 'Upload error: ' . uploadErrorMessage($currentFile['error'])
                        ];
                        continue;
                    }
                    
                    // Check file type
                    $mimeType = $currentFile['type'];
                    $originalFilename = $currentFile['name'];
                    $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
                    
                    if ($fileExtension !== 'pdf' || $mimeType !== 'application/pdf') {
                        $failedFiles[] = [
                            'name' => $originalFilename,
                            'reason' => 'Only PDF files are allowed.'
                        ];
                        continue;
                    }
                    
                    // Check file size
                    if ($currentFile['size'] > getMaxUploadSize()) {
                        $failedFiles[] = [
                            'name' => $originalFilename,
                            'reason' => 'File is too large. Maximum file size is ' . formatBytes(getMaxUploadSize()) . '.'
                        ];
                        continue;
                    }
                    
                    // Generate title from filename (without extension)
                    $title = pathinfo($originalFilename, PATHINFO_FILENAME);
                    
                    // Generate unique filename
                    $newFilename = uniqid() . '.pdf';
                    $uploadPath = '../uploads/' . $newFilename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($currentFile['tmp_name'], $uploadPath)) {
                        try {
                            // Begin transaction
                            $db->beginTransaction();
                            
                            // Generate UUID and short URL
                            $uuid = generateUUID();
                            $shortUrl = generateShortUrl();
                            
                            // Generate QR code
                            $qrCodeUrl = generateQRCode(BASE_URL . 's/' . $shortUrl);
                            
                            // Get user UUID
                            $userUuidStmt = $db->prepare("SELECT uuid FROM users WHERE id = :user_id");
                            $userUuidStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                            $userUuidStmt->execute();
                            $userUuid = $userUuidStmt->fetchColumn();
                            
                            // Save to database
                            $stmt = $db->prepare("INSERT INTO documents (title, description, filename, original_filename, 
                                             file_size, mime_type, short_url, qr_code, user_id, user_uuid, is_public, uuid, created_at, updated_at) 
                                             VALUES (:title, :description, :filename, :original_filename, 
                                             :file_size, :mime_type, :short_url, :qr_code, :user_id, :user_uuid, :is_public, :uuid, 
                                             CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                            
                            $description = ''; // Empty description for bulk uploads
                            
                            $stmt->bindParam(':title', $title);
                            $stmt->bindParam(':description', $description);
                            $stmt->bindParam(':filename', $newFilename);
                            $stmt->bindParam(':original_filename', $originalFilename);
                            $stmt->bindParam(':file_size', $currentFile['size'], PDO::PARAM_INT);
                            $stmt->bindParam(':mime_type', $mimeType);
                            $stmt->bindParam(':short_url', $shortUrl);
                            $stmt->bindParam(':qr_code', $qrCodeUrl);
                            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                            $stmt->bindParam(':user_uuid', $userUuid);
                            $stmt->bindParam(':is_public', $isPublic, PDO::PARAM_INT);
                            $stmt->bindParam(':uuid', $uuid);
                            
                            if ($stmt->execute()) {
                                $documentId = $db->lastInsertId();
                                
                                // Initialize stats for the document
                                $statsStmt = $db->prepare("INSERT INTO stats (document_id, views, downloads, last_view_at) 
                                                      VALUES (:document_id, 0, 0, NULL)");
                                $statsStmt->bindParam(':document_id', $documentId, PDO::PARAM_INT);
                                $statsStmt->execute();
                                
                                // Log the upload activity
                                logActivity('upload', 'document', $documentId, [
                                    'title' => $title,
                                    'file_size' => $currentFile['size'],
                                    'file_type' => $mimeType,
                                    'is_public' => $isPublic,
                                    'bulk_upload' => true
                                ]);
                                
                                // Commit transaction
                                $db->commit();
                                
                                // Add to successful uploads
                                $uploadedFiles[] = [
                                    'name' => $originalFilename,
                                    'id' => $documentId,
                                    'size' => formatBytes($currentFile['size']),
                                    'short_url' => $shortUrl
                                ];
                            } else {
                                // Rollback transaction
                                $db->rollBack();
                                
                                $failedFiles[] = [
                                    'name' => $originalFilename,
                                    'reason' => 'Failed to save document information.'
                                ];
                                
                                // Delete uploaded file if database insert fails
                                @unlink($uploadPath);
                            }
                        } catch (Exception $e) {
                            // Rollback transaction
                            $db->rollBack();
                            
                            $failedFiles[] = [
                                'name' => $originalFilename,
                                'reason' => 'Error saving document: ' . $e->getMessage()
                            ];
                            
                            // Delete uploaded file if database insert fails
                            @unlink($uploadPath);
                        }
                    } else {
                        $failedFiles[] = [
                            'name' => $originalFilename,
                            'reason' => 'Error moving uploaded file.'
                        ];
                    }
                }
                
                if (count($uploadedFiles) > 0) {
                    $success = count($uploadedFiles) . ' document(s) uploaded successfully.';
                }
                
                if (count($failedFiles) > 0) {
                    $error = count($failedFiles) . ' document(s) failed to upload.';
                }
                
                if ($isAjaxRequest) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => count($uploadedFiles) > 0,
                        'message' => $success,
                        'error' => $error,
                        'uploadedFiles' => $uploadedFiles,
                        'failedFiles' => $failedFiles
                    ]);
                    exit;
                }
            }
        }
    }
}

// Include header
$pageTitle = 'Bulk Upload Documents';
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Bulk Upload Documents</h1>
    
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?php echo $error; ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline"><?php echo $success; ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (count($uploadedFiles) > 0): ?>
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-xl font-semibold mb-4">Successfully Uploaded Files</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b text-left">Filename</th>
                            <th class="py-2 px-4 border-b text-left">Size</th>
                            <th class="py-2 px-4 border-b text-left">Short URL</th>
                            <th class="py-2 px-4 border-b text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploadedFiles as $file): ?>
                            <tr>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($file['name']); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo formatBytes($file['size']); ?></td>
                                <td class="py-2 px-4 border-b">
                                    <a href="<?php echo BASE_URL . 's/' . $file['short_url']; ?>" class="text-blue-500 hover:text-blue-700" target="_blank">
                                        <?php echo 's/' . $file['short_url']; ?>
                                    </a>
                                </td>
                                <td class="py-2 px-4 border-b">
                                    <a href="view.php?id=<?php echo $file['id']; ?>" class="text-blue-500 hover:text-blue-700 mr-2">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (count($failedFiles) > 0): ?>
        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <h2 class="text-xl font-semibold mb-4 text-red-600">Failed Uploads</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b text-left">Filename</th>
                            <th class="py-2 px-4 border-b text-left">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failedFiles as $file): ?>
                            <tr>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($file['name']); ?></td>
                                <td class="py-2 px-4 border-b text-red-600"><?php echo htmlspecialchars($file['reason']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <form id="uploadForm" action="bulk-upload.php" method="post" enctype="multipart/form-data" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo getMaxUploadSize(); ?>">
        
        <!-- Drag & Drop Zone -->
        <div class="mb-6">
            <div id="dropZone" class="border-2 border-dashed border-gray-300 rounded-lg p-10 text-center cursor-pointer hover:bg-gray-50 transition-colors duration-300">
                <i class="bi bi-cloud-upload text-5xl text-gray-400 mb-3"></i>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Drag & Drop PDF Files Here</h3>
                <p class="text-sm text-gray-500 mb-3">or</p>
                <label for="pdf_files" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded cursor-pointer transition-colors duration-300">
                    Browse Files
                </label>
                <input type="file" id="pdf_files" name="pdf_files[]" accept="application/pdf" multiple class="hidden" required>
                <p class="text-sm text-gray-500 mt-4">Maximum file size: <?php echo formatBytes(getMaxUploadSize()); ?></p>
                <p class="text-sm text-gray-500">File names will be used as document titles.</p>
            </div>
        </div>
        
        <!-- Selected Files Table -->
        <div id="selectedFiles" class="mb-6 hidden">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Selected Files</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b text-left">Filename</th>
                            <th class="py-2 px-4 border-b text-left">Size</th>
                            <th class="py-2 px-4 border-b text-left">Type</th>
                            <th class="py-2 px-4 border-b text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="fileList">
                        <!-- Files will be listed here dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mb-6">
            <label class="flex items-center">
                <input type="checkbox" name="is_public" value="1" <?php echo $isPublic ? 'checked' : ''; ?> class="mr-2">
                <span class="text-gray-700 text-sm font-bold">Make all documents publicly accessible</span>
            </label>
        </div>
        
        <div class="flex items-center justify-between">
            <button type="submit" id="uploadButton" name="bulk_upload" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors duration-300 flex items-center">
                <i class="bi bi-upload mr-2"></i> Upload Documents
                <span id="uploadCount" class="ml-2 bg-blue-700 text-white text-xs font-bold rounded-full px-2 py-1 hidden">0</span>
            </button>
            <a href="documents.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                Cancel
            </a>
        </div>
        
        <!-- Upload Progress -->
        <div id="uploadProgress" class="mt-4 hidden">
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div id="progressBar" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
            </div>
            <p id="progressText" class="text-sm text-gray-600 mt-1 text-center">0%</p>
        </div>
    </form>
    
    <div class="text-center mt-4">
        <a href="upload.php" class="text-blue-500 hover:text-blue-700">
            <i class="bi bi-arrow-left mr-1"></i> Back to Single Upload
        </a>
    </div>
</div>

<!-- JavaScript for Drag & Drop and File Preview -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('pdf_files');
    const selectedFiles = document.getElementById('selectedFiles');
    const fileList = document.getElementById('fileList');
    const uploadButton = document.getElementById('uploadButton');
    const uploadCount = document.getElementById('uploadCount');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const uploadForm = document.getElementById('uploadForm');
    
    let files = [];
    
    // Highlight drop area when item is dragged over it
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.classList.add('border-blue-500', 'bg-blue-50');
    });
    
    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        dropZone.classList.remove('border-blue-500', 'bg-blue-50');
    });
    
    // Handle dropped files
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('border-blue-500', 'bg-blue-50');
        
        const droppedFiles = e.dataTransfer.files;
        handleFiles(droppedFiles);
    });
    
    // Handle selected files via the file input
    fileInput.addEventListener('change', function() {
        handleFiles(fileInput.files);
    });
    
    // Click on drop zone should trigger file input
    dropZone.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Process the selected files
    function handleFiles(selectedFiles) {
        files = Array.from(selectedFiles).filter(file => file.type === 'application/pdf');
        
        if (files.length === 0) {
            alert('Please select only PDF files.');
            return;
        }
        
        // Display the selected files
        updateFileList();
    }
    
    // Update the file list in the UI
    function updateFileList() {
        // Clear the current list
        fileList.innerHTML = '';
        
        // Add each file to the list
        files.forEach((file, index) => {
            const row = document.createElement('tr');
            
            const nameCell = document.createElement('td');
            nameCell.className = 'py-2 px-4 border-b';
            nameCell.textContent = file.name;
            
            const sizeCell = document.createElement('td');
            sizeCell.className = 'py-2 px-4 border-b';
            sizeCell.textContent = formatBytes(file.size);
            
            const typeCell = document.createElement('td');
            typeCell.className = 'py-2 px-4 border-b';
            typeCell.textContent = file.type;
            
            const actionCell = document.createElement('td');
            actionCell.className = 'py-2 px-4 border-b';
            
            const removeButton = document.createElement('button');
            removeButton.className = 'text-red-500 hover:text-red-700';
            removeButton.innerHTML = '<i class="bi bi-trash"></i>';
            removeButton.onclick = function() {
                files.splice(index, 1);
                updateFileList();
            };
            
            actionCell.appendChild(removeButton);
            
            row.appendChild(nameCell);
            row.appendChild(sizeCell);
            row.appendChild(typeCell);
            row.appendChild(actionCell);
            
            fileList.appendChild(row);
        });
        
        // Show/hide the selected files section
        if (files.length > 0) {
            selectedFiles.classList.remove('hidden');
            uploadCount.textContent = files.length;
            uploadCount.classList.remove('hidden');
        } else {
            selectedFiles.classList.add('hidden');
            uploadCount.classList.add('hidden');
        }
        
        // Create a new FileList for the file input
        // Instead of using DataTransfer API which may not be fully supported
        // We'll create a hidden form element for each file
        const formContainer = document.getElementById('fileFormContainer') || document.createElement('div');
        formContainer.id = 'fileFormContainer';
        formContainer.style.display = 'none';
        formContainer.innerHTML = '';
        
        files.forEach((file, index) => {
            const input = document.createElement('input');
            input.type = 'file';
            input.name = 'pdf_files[]';
            input.classList.add('file-input-clone');
            formContainer.appendChild(input);
        });
        
        if (!document.getElementById('fileFormContainer')) {
            uploadForm.appendChild(formContainer);
        }
    }
    
    // Show upload progress animation when form is submitted
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent standard form submission
        
        if (files.length === 0) {
            alert('Please select at least one PDF file to upload.');
            return;
        }
        
        // Create FormData object
        const formData = new FormData();
        
        // Add CSRF token
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        formData.append('csrf_token', csrfToken);
        
        // Add is_public checkbox value
        const isPublic = document.querySelector('input[name="is_public"]').checked ? 1 : 0;
        formData.append('is_public', isPublic);
        
        // Add bulk_upload flag
        formData.append('bulk_upload', 'true');
        
        // Add each file to FormData
        files.forEach(file => {
            formData.append('pdf_files[]', file);
        });
        
        // Show progress bar
        uploadProgress.classList.remove('hidden');
        uploadButton.disabled = true;
        uploadButton.classList.add('opacity-50', 'cursor-not-allowed');
        
        // Create and send AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'bulk-upload.php', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        // Track upload progress
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressText.textContent = percentComplete + '%';
            }
        };
        
        // Handle response
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    // Parse JSON response
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        // Show success message
                        alert('Upload successful! ' + response.uploadedFiles.length + ' file(s) uploaded.');
                        // Reload the page to show results
                        window.location.reload();
                    } else {
                        // Show error message
                        alert('Upload failed: ' + (response.error || 'Unknown error'));
                        uploadButton.disabled = false;
                        uploadButton.classList.remove('opacity-50', 'cursor-not-allowed');
                        uploadProgress.classList.add('hidden');
                    }
                } catch (e) {
                    // If response is not JSON, reload the page
                    window.location.reload();
                }
            } else {
                alert('Upload failed. Server returned status: ' + xhr.status);
                uploadButton.disabled = false;
                uploadButton.classList.remove('opacity-50', 'cursor-not-allowed');
                uploadProgress.classList.add('hidden');
            }
        };
        
        // Handle network errors
        xhr.onerror = function() {
            alert('Network error occurred. Please try again.');
            uploadButton.disabled = false;
            uploadButton.classList.remove('opacity-50', 'cursor-not-allowed');
            uploadProgress.classList.add('hidden');
        };
        
        // Send the request
        xhr.send(formData);
    });
    
    // Format bytes to human-readable format
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
});
</script>

<?php include 'footer.php'; ?>
