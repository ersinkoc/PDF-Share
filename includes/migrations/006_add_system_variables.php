<?php
/**
 * Migration: Add system variables
 */
function migration_add_system_variables($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS system_variables (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        variable_key TEXT NOT NULL UNIQUE,
        variable_value TEXT,
        description TEXT,
        is_encrypted INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add default system variables
    $systemVariables = [
        ['system.last_backup', '', 'Last database backup date'],
        ['system.installation_id', generateUUID(), 'Unique installation identifier'],
        ['system.is_maintenance_enabled', '0', 'Enable maintenance mode'],
        ['system.installation_date', date('Y-m-d H:i:s'), 'Installation date'],
        ['system.database_version', '1.0', 'Database schema version']
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO system_variables (variable_key, variable_value, description) VALUES (?, ?, ?)");
    
    foreach ($systemVariables as $variable) {
        $stmt->execute($variable);
    }
} 