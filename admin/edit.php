<?php
/**
 * Admin Edit Document
 * 
 * Edit document information
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/utilities.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check if document ID is provided
if (!isset($_GET['uuid']) || empty($_GET['uuid'])) {
    header('Location: documents.php');
    exit;
}

$documentUuid = $_GET['uuid'];
$db = getDbConnection();

// Get document information
$stmt = $db->prepare("SELECT * FROM documents WHERE uuid = :uuid");
$stmt->bindParam(':uuid', $documentUuid);
$stmt->execute();
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: documents.php');
    exit;
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        // Validate title
        if (empty($title)) {
            $error = 'Please enter a document title.';
        } else {
            // Check if a new PDF file was uploaded
            $fileUploaded = false;
            $newFilename = $document['filename']; // Default to current filename
            
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Check for upload errors
                if ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'File upload error: ' . uploadErrorMessage($_FILES['pdf_file']['error']);
                } else {
                    // Check file type
                    $mimeType = $_FILES['pdf_file']['type'];
                    $originalFilename = $_FILES['pdf_file']['name'];
                    $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
                    
                    if ($fileExtension !== 'pdf' || $mimeType !== 'application/pdf') {
                        $error = 'Only PDF files are allowed.';
                    } else {
                        // Check file size
                        if ($_FILES['pdf_file']['size'] > getMaxUploadSize()) {
                            $error = 'File is too large. Maximum file size is ' . formatBytes(getMaxUploadSize()) . '.';
                        } else {
                            // Generate new filename but keep the same path
                            $newFilename = uniqid() . '.pdf';
                            $uploadPath = '../uploads/' . $newFilename;
                            
                            // Move uploaded file
                            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadPath)) {
                                $fileUploaded = true;
                                
                                // Update file information
                                $originalFilename = $_FILES['pdf_file']['name'];
                                $fileSize = $_FILES['pdf_file']['size'];
                                
                                // Delete old file if new file is uploaded successfully
                                if (file_exists('../uploads/' . $document['filename'])) {
                                    @unlink('../uploads/' . $document['filename']);
                                }
                            } else {
                                $error = 'Failed to move uploaded file.';
                            }
                        }
                    }
                }
            }
            
            if (empty($error)) {
                // Begin transaction
                $db->beginTransaction();
                
                try {
                    // Update document information
                    $sql = "UPDATE documents SET 
                            title = :title, 
                            description = :description, 
                            is_public = :is_public, 
                            updated_at = CURRENT_TIMESTAMP";
                    
                    // Add file-related fields if a new file was uploaded
                    if ($fileUploaded) {
                        $sql .= ", filename = :filename, 
                                original_filename = :original_filename, 
                                file_size = :file_size, 
                                mime_type = 'application/pdf'";
                    }
                    
                    $sql .= " WHERE uuid = :uuid";
                    
                    $stmt = $db->prepare($sql);
                    
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':is_public', $isPublic, PDO::PARAM_INT);
                    $stmt->bindParam(':uuid', $documentUuid);
                    
                    // Bind file-related parameters if a new file was uploaded
                    if ($fileUploaded) {
                        $stmt->bindParam(':filename', $newFilename);
                        $stmt->bindParam(':original_filename', $originalFilename);
                        $stmt->bindParam(':file_size', $fileSize, PDO::PARAM_INT);
                    }
                    
                    if ($stmt->execute()) {
                        // Log the edit activity
                        $activityDetails = [
                            'title' => $title,
                            'is_public' => $isPublic,
                            'file_replaced' => $fileUploaded
                        ];
                        
                        logActivity('edit', 'document', $document['id'], $activityDetails);
                        
                        // Commit transaction
                        $db->commit();
                        
                        $success = 'Document updated successfully.';
                        
                        // Refresh document information
                        $stmt = $db->prepare("SELECT * FROM documents WHERE uuid = :uuid");
                        $stmt->bindParam(':uuid', $documentUuid);
                        $stmt->execute();
                        $document = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        // Rollback transaction
                        $db->rollBack();
                        $error = 'Failed to update document information.';
                        
                        // Delete the uploaded file if the database update fails
                        if ($fileUploaded && file_exists($uploadPath)) {
                            @unlink($uploadPath);
                        }
                    }
                } catch (Exception $e) {
                    // Rollback transaction
                    $db->rollBack();
                    $error = 'Error updating document: ' . $e->getMessage();
                    
                    // Delete the uploaded file if an exception occurs
                    if ($fileUploaded && file_exists($uploadPath)) {
                        @unlink($uploadPath);
                    }
                }
            }
        }
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Get page title
$pageTitle = 'Edit Document';
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Edit Document</h1>
        <a href="documents.php" class="text-blue-500 hover:text-blue-700">
            <i class="bi bi-arrow-left mr-1"></i> Back to Documents
        </a>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="mb-4">
                <label for="title" class="block text-gray-700 text-sm font-bold mb-2">Document Title *</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($document['title']); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            
            <div class="mb-4">
                <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                <textarea id="description" name="description" rows="4" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($document['description']); ?></textarea>
            </div>
            
            <div class="mb-4">
                <label for="short_url" class="block text-gray-700 text-sm font-bold mb-2">Short URL</label>
                <div class="flex items-center">
                    <span class="text-gray-600 mr-2"><?php echo BASE_URL; ?>s/</span>
                    <input type="text" id="short_url" value="<?php echo htmlspecialchars($document['short_url']); ?>" class="shadow appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" readonly>
                    <button type="button" onclick="copyToClipboard('<?php echo BASE_URL . 's/' . $document['short_url']; ?>')" class="ml-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">QR Code</label>
                <div class="flex items-center">
                    <img src="<?php echo htmlspecialchars($document['qr_code']); ?>" alt="QR Code" class="w-32 h-32 border">
                    <a href="<?php echo htmlspecialchars($document['qr_code']); ?>" download="qrcode.png" class="ml-4 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        <i class="bi bi-download mr-2"></i> Download QR Code
                    </a>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Current File Information</label>
                <div class="bg-gray-100 p-4 rounded">
                    <p><strong>Original Filename:</strong> <?php echo htmlspecialchars($document['original_filename']); ?></p>
                    <p><strong>File Size:</strong> <?php echo formatFileSize($document['file_size']); ?></p>
                    <p><strong>Uploaded:</strong> <?php echo date('F j, Y, g:i a', strtotime($document['created_at'])); ?></p>
                    <p><strong>Last Updated:</strong> <?php echo date('F j, Y, g:i a', strtotime($document['updated_at'])); ?></p>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="pdf_file" class="block text-gray-700 text-sm font-bold mb-2">Replace PDF File</label>
                <div class="flex items-center">
                    <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf" class="py-2 px-3">
                </div>
                <p class="text-sm text-gray-500 mt-1">Leave empty to keep the current file. Maximum file size: <?php echo formatBytes(getMaxUploadSize()); ?></p>
            </div>
            
            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_public" class="form-checkbox h-5 w-5 text-blue-600" <?php echo $document['is_public'] ? 'checked' : ''; ?>>
                    <span class="ml-2 text-gray-700">Make document publicly accessible</span>
                </label>
            </div>
            
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Save Changes
                </button>
                <a href="documents.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    alert('URL copied to clipboard: ' + text);
}
</script>

<?php include 'footer.php'; ?>
