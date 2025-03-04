<?php
/**
 * Admin View Document
 * 
 * View document details and statistics
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

// Get document information with stats
$stmt = $db->prepare("SELECT d.*, 
                     (SELECT COUNT(*) FROM views WHERE document_uuid = d.uuid) as views, 
                     COALESCE(s.downloads, 0) as downloads, 
                     s.last_view_at, s.last_download_at
                     FROM documents d
                     LEFT JOIN stats s ON d.uuid = s.document_uuid
                     WHERE d.uuid = :uuid");
$stmt->bindParam(':uuid', $documentUuid);
$stmt->execute();
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: documents.php');
    exit;
}

// Get recent views
$viewsStmt = $db->prepare("SELECT ip_address, user_agent, referer, device_type, viewed_at 
                          FROM views 
                          WHERE document_uuid = :uuid 
                          ORDER BY viewed_at DESC 
                          LIMIT 10");
$viewsStmt->bindParam(':uuid', $documentUuid);
$viewsStmt->execute();
$recentViews = $viewsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get previous and next document
$prevStmt = $db->prepare("SELECT uuid, title FROM documents WHERE id < (SELECT id FROM documents WHERE uuid = :uuid) ORDER BY id DESC LIMIT 1");
$prevStmt->bindParam(':uuid', $documentUuid);
$prevStmt->execute();
$prevDoc = $prevStmt->fetch(PDO::FETCH_ASSOC);

$nextStmt = $db->prepare("SELECT uuid, title FROM documents WHERE id > (SELECT id FROM documents WHERE uuid = :uuid) ORDER BY id ASC LIMIT 1");
$nextStmt->bindParam(':uuid', $documentUuid);
$nextStmt->execute();
$nextDoc = $nextStmt->fetch(PDO::FETCH_ASSOC);

// Get page title
$pageTitle = 'View Document';
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Document Navigation -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <?php if ($prevDoc): ?>
        <a href="view.php?uuid=<?php echo $prevDoc['uuid']; ?>" class="group bg-white hover:bg-blue-50 border border-gray-200 rounded-lg p-4 transition-all duration-200 ease-in-out transform hover:-translate-x-1">
            <div class="flex items-center">
                <div class="flex-shrink-0 mr-4">
                    <div class="bg-blue-100 group-hover:bg-blue-200 rounded-full p-3 transition-colors duration-200">
                        <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-500 mb-1">Previous Document</p>
                    <h4 class="text-lg font-medium text-gray-900 truncate group-hover:text-blue-600">
                        <?php echo htmlspecialchars($prevDoc['title']); ?>
                    </h4>
                </div>
            </div>
        </a>
        <?php else: ?>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 opacity-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 mr-4">
                    <div class="bg-gray-100 rounded-full p-3">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-500 mb-1">Previous Document</p>
                    <h4 class="text-lg font-medium text-gray-400">No previous document</h4>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($nextDoc): ?>
        <a href="view.php?uuid=<?php echo $nextDoc['uuid']; ?>" class="group bg-white hover:bg-blue-50 border border-gray-200 rounded-lg p-4 transition-all duration-200 ease-in-out transform hover:translate-x-1">
            <div class="flex items-center justify-end">
                <div class="flex-1 min-w-0 text-right">
                    <p class="text-sm text-gray-500 mb-1">Next Document</p>
                    <h4 class="text-lg font-medium text-gray-900 truncate group-hover:text-blue-600">
                        <?php echo htmlspecialchars($nextDoc['title']); ?>
                    </h4>
                </div>
                <div class="flex-shrink-0 ml-4">
                    <div class="bg-blue-100 group-hover:bg-blue-200 rounded-full p-3 transition-colors duration-200">
                        <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>
        <?php else: ?>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 opacity-50">
            <div class="flex items-center justify-end">
                <div class="flex-1 min-w-0 text-right">
                    <p class="text-sm text-gray-500 mb-1">Next Document</p>
                    <h4 class="text-lg font-medium text-gray-400">No next document</h4>
                </div>
                <div class="flex-shrink-0 ml-4">
                    <div class="bg-gray-100 rounded-full p-3">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">View Document</h1>
        <div>
            <a href="edit.php?uuid=<?php echo $document['uuid']; ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2">
                <i class="bi bi-pencil mr-1"></i> Edit
            </a>
            <a href="documents.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
                <i class="bi bi-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Stats Cards -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Views</h3>
            <p class="text-3xl font-bold"><?php echo $document['views']; ?></p>
            <?php if ($document['last_view_at']): ?>
                <p class="text-sm text-gray-500 mt-2">Last viewed: <?php echo date('M j, Y, g:i a', strtotime($document['last_view_at'])); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Downloads</h3>
            <p class="text-3xl font-bold"><?php echo $document['downloads']; ?></p>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Status</h3>
            <?php if ($document['is_public']): ?>
                <p class="text-green-500 font-bold">Public</p>
            <?php else: ?>
                <p class="text-gray-500 font-bold">Private</p>
            <?php endif; ?>
            <p class="text-sm text-gray-500 mt-2">Created: <?php echo date('M j, Y', strtotime($document['created_at'])); ?></p>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Document Information</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="mb-2"><strong>Title:</strong> <?php echo htmlspecialchars($document['title']); ?></p>
                <p class="mb-2"><strong>Description:</strong> <?php echo !empty($document['description']) ? htmlspecialchars($document['description']) : '<em>No description</em>'; ?></p>
                <p class="mb-2"><strong>Original Filename:</strong> <?php echo htmlspecialchars($document['original_filename']); ?></p>
                <p class="mb-2"><strong>File Size:</strong> <?php echo formatFileSize($document['file_size']); ?></p>
                <p class="mb-2">
                    <strong>Short URL:</strong> 
                    <a href="<?php echo BASE_URL . 's/' . $document['short_url']; ?>" class="text-blue-500 hover:text-blue-700" target="_blank">
                        <?php echo BASE_URL . 's/' . $document['short_url']; ?>
                    </a>
                    <button onclick="copyToClipboard('<?php echo BASE_URL . 's/' . $document['short_url']; ?>')" class="ml-2 text-gray-500 hover:text-gray-700">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </p>
            </div>
            
            <div>
                <p class="mb-4"><strong>QR Code:</strong></p>
                <div class="flex items-center">
                    <img src="<?php echo BASE_URL . 'qr/' . $document['id']; ?>" alt="QR Code" class="w-32 h-32 border">
                    <a href="<?php echo BASE_URL . 'qr/' . $document['id']; ?>" download="qrcode.png" class="ml-4 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        <i class="bi bi-download mr-2"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Preview Document</h2>
        <div class="aspect-w-16 aspect-h-9">
            <iframe src="../view.php?uuid=<?php echo $document['uuid']; ?>&admin=1" class="w-full h-96 border"></iframe>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Recent Views</h2>
        
        <?php if (count($recentViews) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b text-left">Date/Time</th>
                            <th class="py-2 px-4 border-b text-left">IP Address</th>
                            <th class="py-2 px-4 border-b text-left">Device</th>
                            <th class="py-2 px-4 border-b text-left">Referrer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentViews as $view): ?>
                            <tr>
                                <td class="py-2 px-4 border-b"><?php echo date('M j, Y, g:i a', strtotime($view['viewed_at'])); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($view['ip_address']); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($view['device_type']); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo !empty($view['referer']) ? htmlspecialchars($view['referer']) : '<em>Direct</em>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500">No views recorded yet.</p>
        <?php endif; ?>
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
