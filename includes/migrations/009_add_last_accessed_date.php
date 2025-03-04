<?php
/**
 * Add last_accessed_date column to documents table
 */
function migration_add_last_accessed_date($db) {
    // Add last_accessed_date column
    $db->exec("ALTER TABLE documents ADD COLUMN last_accessed_date DATETIME DEFAULT NULL");
    
    // Update existing documents with current timestamp
    $db->exec("UPDATE documents SET last_accessed_date = CURRENT_TIMESTAMP");
    
    // Log the migration
    logActivity('MIGRATION', 'system', 'documents', [
        'action' => 'Added last_accessed_date column to documents table'
    ]);
} 