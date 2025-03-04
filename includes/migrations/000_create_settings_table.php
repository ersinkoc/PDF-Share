<?php
/**
 * Migration: Create settings table
 */
function migration_create_settings_table($db) {
    // Create settings table
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        setting_description TEXT,
        setting_type TEXT DEFAULT 'text',
        is_public INTEGER DEFAULT 0,
        is_editable INTEGER DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert default settings
    $defaultSettings = [
        // General settings
        ['general.site_title', 'PDF QR Link', 'Site Title', 'text', 1, 1],
        ['general.site_description_short', 'Modern PDF Sharing Platform', 'Short Description', 'text', 1, 1],
        ['general.site_description', 'Share PDF documents with ease. Upload your PDFs and get a secure link immediately.', 'Site Description', 'text', 1, 1],
        ['general.admin_email', 'admin@example.com', 'Admin Email', 'email', 0, 1],
        ['general.items_per_page', '10', 'Items Per Page', 'number', 0, 1],
        
        // Upload settings
        ['upload.max_file_size', '10485760', 'Maximum File Size (bytes)', 'number', 0, 1],
        
        // Security settings
        ['security.session_timeout', '3600', 'Session Timeout (seconds)', 'number', 0, 1],
        ['security.max_login_attempts', '5', 'Maximum Login Attempts', 'number', 0, 1],
        
        // QR Code settings
        ['qrcode.size', '5', 'QR Code Size', 'number', 0, 1],
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO settings 
        (setting_key, setting_value, setting_description, setting_type, is_public, is_editable) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
} 