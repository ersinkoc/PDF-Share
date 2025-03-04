<?php
/**
 * Migration: Add storage settings
 */
function migration_add_storage_settings($db) {
    $settings = [
        ['storage.max_space', '1048576000', 'Maximum storage space (bytes, default 1000MB)', 'number', 0, 1],
        ['storage.warning_threshold', '80', 'Storage warning threshold percentage', 'number', 0, 1]
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO settings 
        (setting_key, setting_value, setting_description, setting_type, is_public, is_editable) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
} 