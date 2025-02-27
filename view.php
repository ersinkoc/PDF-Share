<?php
/**
 * View Document
 * 
 * Displays a PDF document for viewing
 */

// Define base path if not defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);
}

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/utilities.php';

// Initialize variables
$documentUuid = null;
$db = getDbConnection();

// Check if document UUID is provided
if (isset($_GET['uuid']) && !empty($_GET['uuid'])) {
    $documentUuid = $_GET['uuid'];
    
    // Validate UUID format
    if (!isValidUUID($documentUuid)) {
        http_response_code(404);
        include '404.php';
        exit;
    }
}
// Check if it's a short URL
else if (isset($_GET['short_url']) && !empty($_GET['short_url'])) {
    $shortUrl = $_GET['short_url'];
    
    $stmt = $db->prepare("SELECT uuid FROM documents WHERE short_url = :short_url");
    $stmt->bindParam(':short_url', $shortUrl);
    $stmt->execute();
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($document && !empty($document['uuid'])) {
        $documentUuid = $document['uuid'];
    } else {
        http_response_code(404);
        include '404.php';
        exit;
    }
} 
// No identifier provided
else {
    http_response_code(404);
    include '404.php';
    exit;
}

$isAdmin = isset($_GET['admin']) && $_GET['admin'] == 1;

// Get document information
$stmt = $db->prepare("SELECT * FROM documents WHERE uuid = :uuid" . ($isAdmin ? "" : " AND is_public = 1"));
$stmt->bindParam(':uuid', $documentUuid);
$stmt->execute();
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    // Document not found or not public
    http_response_code(404);
    include '404.php';
    exit;
}

// Track view if not admin
if (!$isAdmin) {
    // Log the view activity
    logActivity('view', 'document', $document['uuid'], [
        'title' => $document['title'],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ], null);
    
    try {
        // Record view details
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $deviceType = detectDeviceType();
        
        $viewStmt = $db->prepare("INSERT INTO views (document_id, document_uuid, ip_address, user_agent, referer, device_type, viewed_at) 
                                VALUES (:document_id, :document_uuid, :ip_address, :user_agent, :referer, :device_type, CURRENT_TIMESTAMP)");
        $viewStmt->bindParam(':document_id', $document['id']);
        $viewStmt->bindParam(':document_uuid', $documentUuid);
        $viewStmt->bindParam(':ip_address', $ipAddress);
        $viewStmt->bindParam(':user_agent', $userAgent);
        $viewStmt->bindParam(':referer', $referer);
        $viewStmt->bindParam(':device_type', $deviceType);
        $viewStmt->execute();
    } catch (PDOException $e) {
        // Log error but continue with view
        error_log("Error recording view stats: " . $e->getMessage());
    }
    
    // Check if mobile and redirect if needed
    if ($deviceType === 'mobile') {
        // Get setting for mobile viewer
        $useGoogleViewer = true;
        $settingStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'display.use_google_viewer'");
        $setting = $settingStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($setting && $setting['setting_value'] == '1' && !isset($_GET['native'])) {
            header('Location: mobile_view.php?uuid=' . $document['uuid']);
            exit;
        }
    }
}

// Get file path
$filePath = 'uploads/' . $document['filename'];

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - PDF QR Link</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@2.12.313/build/pdf.min.js"></script>
    <style>
        #pdf-container {
            width: 100%;
            height: calc(100vh - 120px);
            overflow: auto;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-bold text-gray-800 truncate"><?php echo htmlspecialchars($document['title']); ?></h1>
                <div class="flex space-x-2">
                    <a href="download.php?uuid=<?php echo $document['uuid']; ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        <i class="bi bi-download mr-1"></i> Download
                    </a>
                    <?php if (!$isAdmin): ?>
                        <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
                            <i class="bi bi-house mr-1"></i> Home
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <!-- PDF Viewer -->
    <div class="container mx-auto px-4 py-4">
        <div id="pdf-container" class="bg-white rounded-lg shadow">
            <div id="pdf-viewer" class="w-full h-full"></div>
        </div>
    </div>
    
    <script>
        // PDF.js viewer
        const url = '<?php echo $filePath; ?>';
        
        // The workerSrc property needs to be specified
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@2.12.313/build/pdf.worker.min.js';
        
        // Load the PDF
        const loadingTask = pdfjsLib.getDocument(url);
        loadingTask.promise.then(function(pdf) {
            // Get the first page
            pdf.getPage(1).then(function(page) {
                const scale = 1.5;
                const viewport = page.getViewport({ scale: scale });
                
                // Prepare canvas using PDF page dimensions
                const container = document.getElementById('pdf-viewer');
                
                // Create and append canvas for each page
                for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                    const canvas = document.createElement('canvas');
                    canvas.className = 'mb-4';
                    container.appendChild(canvas);
                    
                    const context = canvas.getContext('2d');
                    
                    // Get the page
                    pdf.getPage(pageNum).then(function(page) {
                        const viewport = page.getViewport({ scale: scale });
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        
                        // Render PDF page into canvas context
                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };
                        
                        page.render(renderContext);
                    });
                }
            });
        });
    </script>
</body>
</html>
