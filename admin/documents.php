<?php
/**
 * Admin Documents
 * 
 * Manages all uploaded documents
 */
// Include required files
require_once '../includes/init.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get database connection
$db = getDbConnection();

// Handle document deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $documentUuid = $_GET['delete'];
    $csrfToken = $_GET['token'] ?? '';
    
    if (validateCSRFToken($csrfToken)) {
        // Get document filename
        $stmt = $db->prepare("SELECT filename FROM documents WHERE uuid = :uuid");
        $stmt->bindParam(':uuid', $documentUuid);
        $stmt->execute();
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            // Delete file from filesystem
            $filePath = '../uploads/' . $document['filename'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            
            // Delete from database
            $deleteStmt = $db->prepare("DELETE FROM documents WHERE uuid = :uuid");
            $deleteStmt->bindParam(':uuid', $documentUuid);
            
            if ($deleteStmt->execute()) {
                // Delete associated stats
                $statsStmt = $db->prepare("DELETE FROM stats WHERE document_uuid = :uuid");
                $statsStmt->bindParam(':uuid', $documentUuid);
                $statsStmt->execute();
                
                // Delete associated views
                $viewsStmt = $db->prepare("DELETE FROM views WHERE document_uuid = :uuid");
                $viewsStmt->bindParam(':uuid', $documentUuid);
                $viewsStmt->execute();
                
                $successMessage = 'Document deleted successfully.';
            } else {
                $errorMessage = 'Failed to delete document.';
            }
        } else {
            $errorMessage = 'Document not found.';
        }
    } else {
        $errorMessage = 'Invalid token. Please try again.';
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Get pagination setting from database
$settingStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'general.items_per_page'");
$setting = $settingStmt->fetch(PDO::FETCH_ASSOC);
$perPage = ($setting && !empty($setting['setting_value'])) ? (int)$setting['setting_value'] : 10;

$offset = ($page - 1) * $perPage;

// Get documents with pagination
$totalStmt = $db->query("SELECT COUNT(*) FROM documents");
$totalDocuments = $totalStmt->fetchColumn();
$totalPages = ceil($totalDocuments / $perPage);

// Get documents with stats data
$stmt = $db->prepare("SELECT d.uuid, d.title, d.short_url, d.is_public, d.created_at,
                    (SELECT COUNT(*) FROM views WHERE document_uuid = d.uuid) as views,
                    COALESCE(s.downloads, 0) as downloads
                    FROM documents d
                    LEFT JOIN stats s ON d.uuid = s.document_uuid
                    ORDER BY d.created_at DESC
                    LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Get page title
$pageTitle = 'Manage Documents';
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Manage Documents</h1>
        <a href="upload.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="bi bi-upload mr-2"></i> Upload New
        </a>
    </div>
    
    <?php if (isset($successMessage)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo htmlspecialchars($successMessage); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errorMessage)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo htmlspecialchars($errorMessage); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php if (count($documents) > 0): ?>
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Title</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Short URL</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Uploaded</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Views</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Downloads</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($doc['title']); ?></td>
                            <td class="py-3 px-4">
                                <a href="<?php echo BASE_URL . 's/' . $doc['short_url']; ?>" class="text-blue-500 hover:text-blue-700" target="_blank">
                                    <?php echo 's/' . $doc['short_url']; ?>
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($doc['is_public']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Public
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Private
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4"><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></td>
                            <td class="py-3 px-4"><?php echo $doc['views']; ?></td>
                            <td class="py-3 px-4"><?php echo $doc['downloads']; ?></td>
                            <td class="py-3 px-4">
                                <a href="view.php?uuid=<?php echo $doc['uuid']; ?>" class="text-blue-500 hover:text-blue-700 mr-2" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit.php?uuid=<?php echo $doc['uuid']; ?>" class="text-green-500 hover:text-green-700 mr-2" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="#" onclick="confirmDelete('<?php echo $doc['uuid']; ?>', '<?php echo $csrfToken; ?>')" class="text-red-500 hover:text-red-700" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $perPage, $totalDocuments); ?></span> of <span class="font-medium"><?php echo $totalDocuments; ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $startPage + 4);
                                if ($endPage - $startPage < 4) {
                                    $startPage = max(1, $endPage - 4);
                                }
                                ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="p-6 text-center">
                <p class="text-gray-500">No documents found.</p>
                <a href="upload.php" class="mt-4 inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Upload Your First Document
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(uuid, token) {
    if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
        window.location.href = 'documents.php?delete=' + uuid + '&token=' + token;
    }
}
</script>

<?php include 'footer.php'; ?>
