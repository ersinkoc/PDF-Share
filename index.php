<?php
/**
 * Main Entry Point
 * 
 * Handles routing for the application
 */

// Define base path
define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/utilities.php';
require_once 'includes/error_handler.php';

// Check if it's a short URL via GET parameter (from .htaccess rewrite)
if (isset($_GET['short_url']) && !empty($_GET['short_url'])) {
    $shortCode = $_GET['short_url'];
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT uuid FROM documents WHERE short_url = :short_url");
    $stmt->bindParam(':short_url', $shortCode);
    $stmt->execute();
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($document) {
        // Redirect to view page using UUID
        header("Location: " . BASE_URL . "view.php?uuid=" . $document['uuid']);
        exit;
    } else {
        // Short URL not found
        http_response_code(404);
        include '404.php';
        exit;
    }
}

// Get request URI
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = trim($requestUri, '/');

// Handle short URLs
if (preg_match('#^s/([a-zA-Z0-9]+)$#', $requestUri, $matches)) {
    $shortUrl = $matches[1];
    
    // Get document ID from short URL
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT id, uuid FROM documents WHERE short_url = :short_url");
    $stmt->bindParam(':short_url', $shortUrl);
    $stmt->execute();
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($document) {
        // Redirect to view page using UUID if available, otherwise fallback to ID
        if (!empty($document['uuid'])) {
            header("Location: " . BASE_URL . "view.php?uuid=" . $document['uuid']);
        } else {
            header("Location: " . BASE_URL . "view.php?id=" . $document['id']);
        }
        exit;
    } else {
        // Short URL not found
        http_response_code(404);
        include '404.php';
        exit;
    }
}

// Handle QR code requests
if (preg_match('#^qr/([0-9]+)$#', $requestUri, $matches)) {
    $_GET['id'] = $matches[1];
    include 'qr.php';
    exit;
}

// Handle admin routes
if (strpos($requestUri, 'admin') === 0) {
    $adminPath = substr($requestUri, 5);
    
    if (empty($adminPath) || $adminPath === '/') {
        include 'admin/index.php';
        exit;
    }
    
    $adminFile = 'admin' . $adminPath . '.php';
    if (file_exists($adminFile)) {
        include $adminFile;
        exit;
    }
    
    // Admin file not found, show 404
    include '404.php';
    exit;
}

// Default route
switch ($requestUri) {
    case '':
    case 'index.php':
        include 'home.php';
        break;
    case 'view':
    case 'view.php':
        include 'view.php';
        break;
    case 'download':
    case 'download.php':
        include 'download.php';
        break;
    case 'mobile_view':
    case 'mobile_view.php':
        include 'mobile_view.php';
        break;
    default:
        // 404 Not Found
        http_response_code(404);
        include '404.php';
        break;
}
