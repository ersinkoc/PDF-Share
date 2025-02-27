<?php
/**
 * Mobile View
 * 
 * Mobile-optimized view for PDF documents
 */

// Define base path if not defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);
}

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/utilities.php';

// Check if document UUID is provided
if (!isset($_GET['uuid']) || !isValidUUID($_GET['uuid'])) {
    http_response_code(404);
    include '404.php';
    exit;
}

$documentUuid = $_GET['uuid'];

// Get document information
$db = getDbConnection();
$stmt = $db->prepare("SELECT * FROM documents WHERE uuid = :uuid AND is_public = 1");
$stmt->bindParam(':uuid', $documentUuid, PDO::PARAM_STR);
$stmt->execute();
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    // Document not found or not public
    http_response_code(404);
    include '404.php';
    exit;
}

// Track view (only if not already tracked in view.php)
if (isset($_GET['track']) && $_GET['track'] == 1) {
    // Update view count
    $stmt = $db->prepare("UPDATE documents SET view_count = view_count + 1 WHERE uuid = :uuid");
    $stmt->bindParam(':uuid', $documentUuid, PDO::PARAM_STR);
    $stmt->execute();
    
    // Record view details
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $deviceType = 'mobile';
    
    $viewStmt = $db->prepare("INSERT INTO views (document_uuid, ip_address, user_agent, referer, device_type, viewed_at) 
                             VALUES (:document_uuid, :ip_address, :user_agent, :referer, :device_type, CURRENT_TIMESTAMP)");
    $viewStmt->bindParam(':document_uuid', $documentUuid, PDO::PARAM_STR);
    $viewStmt->bindParam(':ip_address', $ipAddress);
    $viewStmt->bindParam(':user_agent', $userAgent);
    $viewStmt->bindParam(':referer', $referer);
    $viewStmt->bindParam(':device_type', $deviceType);
    $viewStmt->execute();
}

// Get file path and URL
$filePath = 'uploads/' . $document['filename'];
$fileUrl = BASE_URL . $filePath;

// Check if file exists
if (!file_exists($filePath)) {
    // File not found
    http_response_code(404);
    include '404.php';
    exit;
}

// Page title
$pageTitle = $document['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($pageTitle); ?> - PDF QR Link</title>
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">
        @tailwind base;
        @tailwind components;
        @tailwind utilities;
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        body {
            overscroll-behavior: none;
        }
        .iframe-container {
            width: 100%;
            height: calc(100vh - 64px);
            overflow: hidden;
        }
        .iframe-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow fixed top-0 left-0 right-0 z-10">
        <div class="px-4 py-3">
            <div class="flex justify-between items-center">
                <h1 class="text-lg font-bold text-gray-800 truncate"><?php echo htmlspecialchars($document['title']); ?></h1>
                <div class="flex space-x-2">
                    <a href="download.php?uuid=<?php echo $document['uuid']; ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm">
                        <i class="bi bi-download"></i>
                    </a>
                    <a href="view.php?uuid=<?php echo $document['uuid']; ?>&native=1" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-3 rounded text-sm">
                        <i class="bi bi-eye"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- PDF Viewer (Google Docs) -->
    <div class="iframe-container mt-16">
        <iframe src="https://docs.google.com/viewer?url=<?php echo urlencode($fileUrl); ?>&embedded=true" allowfullscreen></iframe>
    </div>
    
    <script>
        // Prevent overscroll/bounce effect on mobile
        document.addEventListener('touchmove', function(e) {
            e.preventDefault();
        }, { passive: false });
    </script>
</body>
</html>
