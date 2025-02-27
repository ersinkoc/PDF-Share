<?php
/**
 * Upload Document Page
 * 
 * Allows administrators to upload new PDF documents
 */

// Include initialization file
require_once '../includes/init.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Initialize variables
$title = '';
$description = '';
$isPublic = 0;
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        // Get form data
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        // Validate form data
        if (empty($title)) {
            $error = 'Title is required.';
        } elseif (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Please select a PDF file to upload.';
        } elseif ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload error: ' . uploadErrorMessage($_FILES['pdf_file']['error']);
        } else {
            // Check file type
            $mimeType = $_FILES['pdf_file']['type'];
            $originalFilename = $_FILES['pdf_file']['name'];
            $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            
            if ($fileExtension !== 'pdf' || $mimeType !== 'application/pdf') {
                $error = 'Only PDF files are allowed.';
            } else {
                // Check file size
                if ($_FILES['pdf_file']['size'] > MAX_FILE_SIZE) {
                    $error = 'File is too large. Maximum file size is ' . formatBytes(MAX_FILE_SIZE) . '.';
                } else {
                    // Check total storage usage
                    $storageUsage = getTotalStorageUsage();
                    if ($storageUsage['total_size'] + $_FILES['pdf_file']['size'] > MAX_STORAGE_SIZE) {
                        $error = 'Storage limit exceeded. Maximum storage size is ' . formatBytes(MAX_STORAGE_SIZE) . '.';
                    } else {
                        // Create upload directory if it doesn't exist
                        if (!file_exists('../uploads')) {
                            mkdir('../uploads', 0755, true);
                        }
                        
                        // Generate unique filename
                        $newFilename = uniqid() . '.pdf';
                        $uploadPath = '../uploads/' . $newFilename;
                        
                        // Move uploaded file
                        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadPath)) {
                            // Generate UUID and short URL
                            $uuid = generateUUID();
                            $shortUrl = generateShortUrl();
                            
                            // Generate QR code
                            $qrCodeUrl = generateQRCode(BASE_URL . 's/' . $shortUrl);
                            
                            // Save to database
                            $db = getDbConnection();
                            
                            try {
                                // Begin transaction
                                $db->beginTransaction();
                                
                                // Get user UUID
                                $userUuidStmt = $db->prepare("SELECT uuid FROM users WHERE id = :user_id");
                                $userUuidStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                                $userUuidStmt->execute();
                                $userUuid = $userUuidStmt->fetchColumn();
                                
                                $stmt = $db->prepare("INSERT INTO documents (title, description, filename, original_filename, 
                                                 file_size, mime_type, short_url, qr_code, user_id, user_uuid, is_public, uuid, created_at, updated_at) 
                                                 VALUES (:title, :description, :filename, :original_filename, 
                                                 :file_size, :mime_type, :short_url, :qr_code, :user_id, :user_uuid, :is_public, :uuid, 
                                                 CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                                
                                $stmt->bindParam(':title', $title);
                                $stmt->bindParam(':description', $description);
                                $stmt->bindParam(':filename', $newFilename);
                                $stmt->bindParam(':original_filename', $originalFilename);
                                $stmt->bindParam(':file_size', $_FILES['pdf_file']['size'], PDO::PARAM_INT);
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
                                        'file_size' => $_FILES['pdf_file']['size'],
                                        'file_type' => $mimeType,
                                        'is_public' => $isPublic
                                    ]);
                                    
                                    // Commit transaction
                                    $db->commit();
                                    
                                    $success = 'Document uploaded successfully.';
                                    
                                    // Clear form data
                                    $title = $description = '';
                                    $isPublic = 0;
                                } else {
                                    // Rollback transaction
                                    $db->rollBack();
                                    
                                    $error = 'Failed to save document information.';
                                    // Delete uploaded file if database insert fails
                                    @unlink($uploadPath);
                                }
                            } catch (Exception $e) {
                                // Rollback transaction
                                $db->rollBack();
                                
                                $error = 'Error saving document: ' . $e->getMessage();
                                // Delete uploaded file if database insert fails
                                @unlink($uploadPath);
                            }
                        } else {
                            $error = 'Error moving uploaded file.';
                        }
                    }
                }
            }
        }
    }
}

// Include header
$pageTitle = 'Upload Document';
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Upload New Document</h1>
    
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
    
    <form action="upload.php" method="post" enctype="multipart/form-data" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo getMaxUploadSize(); ?>">
        
        <div class="mb-4">
            <label for="title" class="block text-gray-700 text-sm font-bold mb-2">Title *</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        </div>
        
        <div class="mb-4">
            <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description</label>
            <textarea id="description" name="description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline h-32"><?php echo htmlspecialchars($description); ?></textarea>
        </div>
        
        <div class="mb-4">
            <label for="pdf_file" class="block text-gray-700 text-sm font-bold mb-2">PDF File *</label>
            <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            <p class="text-sm text-gray-500 mt-1">Maximum file size: <?php echo formatFileSize(getMaxUploadSize()); ?></p>
        </div>
        
        <div class="mb-6">
            <label class="flex items-center">
                <input type="checkbox" name="is_public" value="1" <?php echo $isPublic ? 'checked' : ''; ?> class="mr-2">
                <span class="text-gray-700 text-sm font-bold">Make document publicly accessible</span>
            </label>
        </div>
        
        <div class="flex items-center justify-between">
            <button type="submit" name="upload" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="bi bi-upload mr-2"></i> Upload Document
            </button>
            <a href="documents.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                Cancel
            </a>
        </div>
    </form>
    
    <div class="text-center mt-4">
        <a href="bulk-upload.php" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline inline-block">
            <i class="bi bi-files mr-1"></i> Switch to Bulk Upload
        </a>
    </div>
</div>

<?php include 'footer.php'; ?>
