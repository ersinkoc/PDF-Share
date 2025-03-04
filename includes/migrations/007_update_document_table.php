<?php
/**
 * Migration: Update document table
 */
function migration_update_document_table($db) {
    // Check if download_count column exists
    $result = $db->query("PRAGMA table_info(documents)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $hasDownloadCount = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'download_count') {
            $hasDownloadCount = true;
            break;
        }
    }
    
    // Add download_count column if it doesn't exist
    if (!$hasDownloadCount) {
        $db->exec("ALTER TABLE documents ADD COLUMN download_count INTEGER DEFAULT 0");
    }
    
    // Check if expiry_date column exists
    $hasExpiryDate = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'expiry_date') {
            $hasExpiryDate = true;
            break;
        }
    }
    
    // Add expiry_date column if it doesn't exist
    if (!$hasExpiryDate) {
        $db->exec("ALTER TABLE documents ADD COLUMN expiry_date DATETIME DEFAULT NULL");
    }
} 