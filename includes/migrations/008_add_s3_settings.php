<?php
/**
 * Migration: Add S3 storage settings
 */
function migration_add_s3_settings($db) {
    $settings = [
        ['s3.provider', 's3', 'Storage provider (s3, minio)', 'text', 0, 1],
        ['s3.endpoint', '', 'S3 endpoint URL (for MinIO or custom S3)', 'text', 0, 1],
        ['s3.region', '', 'S3 region', 'text', 0, 1],
        ['s3.bucket', '', 'S3 bucket name', 'text', 0, 1],
        ['s3.access_key', '', 'S3 access key', 'text', 0, 1],
        ['s3.secret_key', '', 'S3 secret key', 'text', 0, 1],
        ['s3.use_path_style', '0', 'Use path style endpoints', 'boolean', 0, 1],
        ['s3.ssl_verify', '1', 'Verify SSL certificates', 'boolean', 0, 1]
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO settings 
        (setting_key, setting_value, setting_description, setting_type, is_public, is_editable) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
} 