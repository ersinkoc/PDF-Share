<?php
/**
 * Delete Document
 * 
 * Handles document deletion with confirmation
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Get document ID and UUID from URL
$documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$documentUuid = isset($_GET['uuid']) ? $_GET['uuid'] : '';

// Check if document exists
$db = getDbConnection();

if (!empty($documentUuid)) {
    // Use UUID if provided
    $stmt = $db->prepare("SELECT id, uuid, title, filename FROM documents WHERE uuid = :uuid");
    $stmt->bindParam(':uuid', $documentUuid);
} else {
    // Fallback to ID for backward compatibility
    $stmt = $db->prepare("SELECT id, uuid, title, filename FROM documents WHERE id = :id");
    $stmt->bindParam(':id', $documentId, PDO::PARAM_INT);
}

$stmt->execute();
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    // Document not found, redirect to dashboard
    header('Location: index.php');
    exit;
}

// Check if form was submitted (confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Get document details for logging
        $stmt = $db->prepare("SELECT title, file_size, file_type, is_public FROM documents WHERE id = :id");
        $stmt->bindParam(':id', $document['id'], PDO::PARAM_INT);
        $stmt->execute();
        $docDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete the physical file
        $filePath = UPLOAD_DIR . '/' . $document['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete document from database (stats will be deleted via CASCADE)
        $stmt = $db->prepare("DELETE FROM documents WHERE id = :id");
        $stmt->bindParam(':id', $document['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Log the deletion activity
        logActivity('delete', 'document', $document['id'], [
            'title' => $docDetails['title'],
            'file_size' => $docDetails['file_size'],
            'file_type' => $docDetails['file_type']
        ]);
        
        // Commit transaction
        $db->commit();
        
        // Redirect to dashboard with success message
        $_SESSION['flash_message'] = 'Document deleted successfully.';
        $_SESSION['flash_type'] = 'success';
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        
        // Log error
        logError('Failed to delete document: ' . $e->getMessage());
        
        // Set error message
        $_SESSION['flash_message'] = 'Failed to delete document: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
}

// Include header
$pageTitle = 'Delete Document';
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Delete Document</h1>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-2">Confirm Deletion</h2>
            <p class="text-gray-600 mb-4">
                Are you sure you want to delete the document <strong><?php echo htmlspecialchars($document['title']); ?></strong>?
                This action cannot be undone.
            </p>
            
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="bi bi-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            Warning: This will permanently delete the document and all associated statistics.
                            Any links to this document will no longer work.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <form method="post" class="flex space-x-4">
            <button type="submit" name="confirm_delete" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                <i class="bi bi-trash mr-2"></i> Delete Document
            </button>
            <a href="index.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                <i class="bi bi-x-circle mr-2"></i> Cancel
            </a>
        </form>
    </div>
</div>

<?php include_once 'footer.php'; ?>
