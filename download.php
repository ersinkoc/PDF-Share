<?php
/**
 * Download Document
 * 
 * Handles document downloads
 */

// Define base path if not defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);
}

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/utilities.php';

// Helper function to generate UUID if not available from utilities
if (!function_exists('generateUUID')) {
    function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// Check if document UUID is provided
if (!isset($_GET['uuid']) || !isValidUUID($_GET['uuid'])) {
    http_response_code(404);
    include '404.php';
    exit;
}

$documentUuid = $_GET['uuid'];
$isAdmin = isset($_GET['admin']) && $_GET['admin'] == 1;

// Get document information
$db = getDbConnection();
$stmt = $db->prepare("SELECT * FROM documents WHERE uuid = :uuid" . ($isAdmin ? "" : " AND is_public = 1"));
$stmt->bindParam(':uuid', $documentUuid, PDO::PARAM_STR);
$stmt->execute();
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    // Document not found or not public
    http_response_code(404);
    include '404.php';
    exit;
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

// Track download if not admin
if (!$isAdmin) {
    try {
        // Check if stats record exists for this document
        $checkStmt = $db->prepare("SELECT * FROM stats WHERE document_uuid = :uuid");
        $checkStmt->bindParam(':uuid', $documentUuid, PDO::PARAM_STR);
        $checkStmt->execute();
        $statsRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($statsRecord) {
            // Update existing stats record
            $updateStmt = $db->prepare("UPDATE stats SET downloads = downloads + 1, last_download_at = CURRENT_TIMESTAMP WHERE document_uuid = :uuid");
            $updateStmt->bindParam(':uuid', $documentUuid, PDO::PARAM_STR);
            $updateStmt->execute();
        } else {
            // Check if any record exists with this document_id to avoid constraint violation
            $checkIdStmt = $db->prepare("SELECT * FROM stats WHERE document_id = :id");
            $checkIdStmt->bindParam(':id', $document['id'], PDO::PARAM_INT);
            $checkIdStmt->execute();
            $existingRecord = $checkIdStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRecord) {
                // Update the existing record with the UUID
                $updateIdStmt = $db->prepare("UPDATE stats SET document_uuid = :uuid, downloads = downloads + 1, last_download_at = CURRENT_TIMESTAMP WHERE document_id = :id");
                $updateIdStmt->bindParam(':uuid', $documentUuid, PDO::PARAM_STR);
                $updateIdStmt->bindParam(':id', $document['id'], PDO::PARAM_INT);
                $updateIdStmt->execute();
            } else {
                // Create new stats record with UUID
                $statsUuid = generateUUID();
                $insertStmt = $db->prepare("INSERT INTO stats (uuid, document_id, document_uuid, downloads, last_download_at) VALUES (:uuid, :document_id, :document_uuid, 1, CURRENT_TIMESTAMP)");
                $insertStmt->bindParam(':uuid', $statsUuid, PDO::PARAM_STR);
                $insertStmt->bindParam(':document_id', $document['id'], PDO::PARAM_INT);
                $insertStmt->bindParam(':document_uuid', $documentUuid, PDO::PARAM_STR);
                $insertStmt->execute();
            }
        }
        
        // Log the download activity
        logActivity('download', 'document', $document['uuid'], [
            'title' => $document['title'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], null);
    } catch (PDOException $e) {
        // Log error but continue with download
        error_log("Error updating download stats: " . $e->getMessage());
    }
}

// Set headers for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $document['original_filename'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($filePath);
exit;
