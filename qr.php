<?php
/**
 * QR Code Generator
 * 
 * Generates and serves QR codes for document URLs
 */

// Include required files
require_once 'includes/init.php';

// Check if document ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.0 404 Not Found');
    exit('QR code not found');
}

$documentId = (int)$_GET['id'];
$db = getDbConnection();

// Get document information
$stmt = $db->prepare("SELECT short_url FROM documents WHERE id = :id");
$stmt->bindParam(':id', $documentId, PDO::PARAM_INT);
$stmt->execute();
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('HTTP/1.0 404 Not Found');
    exit('QR code not found');
}

// Generate QR code URL
$documentUrl = BASE_URL . 's/' . $document['short_url'];
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($documentUrl);

// Create a unique hash for the URL
$urlHash = md5($documentUrl);
$cacheFile = CACHE_DIR . $urlHash . '.png';

// Check if cache directory exists
if (!file_exists(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

// If QR code already exists in cache, serve it
if (file_exists($cacheFile)) {
    header('Content-Type: image/png');
    readfile($cacheFile);
    exit;
}

// Try to download QR code with error handling
try {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5 // 5 seconds timeout
        ]
    ]);
    
    $qrCode = @file_get_contents($qrUrl, false, $context);
    
    if ($qrCode !== false) {
        // Save to cache
        file_put_contents($cacheFile, $qrCode);
        
        // Output QR code
        header('Content-Type: image/png');
        echo $qrCode;
        exit;
    }
} catch (Exception $e) {
    // Log error
    error_log("QR code generation failed: " . $e->getMessage());
}

// Return fallback if download fails
$fallbackImage = 'assets/img/qr-placeholder.png';
if (file_exists($fallbackImage)) {
    header('Content-Type: image/png');
    readfile($fallbackImage);
} else {
    header('HTTP/1.0 404 Not Found');
    exit('QR code generation failed');
}
