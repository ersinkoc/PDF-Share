<?php
/**
 * Migration: Create audit log table
 */
function migration_create_audit_log_table($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT UNIQUE,
        user_id INTEGER,
        user_uuid TEXT,
        action TEXT NOT NULL,
        entity_type TEXT NOT NULL,
        entity_id TEXT NOT NULL,
        entity_uuid TEXT,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (user_uuid) REFERENCES users(uuid)
    )");
} 