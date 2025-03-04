<?php
/**
 * Migration: Create initial tables
 * 
 * This migration creates the basic tables needed for the system.
 * Note: Settings table is created in a separate migration that runs first.
 */
function migration_create_tables($db) {
    // Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT UNIQUE,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        email TEXT,
        is_admin INTEGER DEFAULT 0,
        last_login TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create documents table
    $db->exec("CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT UNIQUE,
        title TEXT NOT NULL,
        description TEXT,
        filename TEXT NOT NULL,
        original_filename TEXT NOT NULL,
        file_size INTEGER NOT NULL,
        mime_type TEXT NOT NULL,
        short_url TEXT NOT NULL UNIQUE,
        qr_code TEXT NOT NULL,
        user_id INTEGER NOT NULL,
        user_uuid TEXT,
        is_public INTEGER DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (user_uuid) REFERENCES users(uuid)
    )");
    
    // Create stats table
    $db->exec("CREATE TABLE IF NOT EXISTS stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT UNIQUE,
        document_id INTEGER,
        document_uuid TEXT UNIQUE,
        views INTEGER DEFAULT 0,
        downloads INTEGER DEFAULT 0,
        last_view_at DATETIME,
        last_download_at DATETIME,
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (document_uuid) REFERENCES documents(uuid) ON DELETE CASCADE
    )");
    
    // Create views table
    $db->exec("CREATE TABLE IF NOT EXISTS views (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT UNIQUE,
        document_id INTEGER NOT NULL,
        document_uuid TEXT,
        ip_address TEXT,
        user_agent TEXT,
        referer TEXT,
        device_type TEXT,
        country TEXT,
        city TEXT,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (document_uuid) REFERENCES documents(uuid) ON DELETE CASCADE
    )");
    
    // Create tags table
    $db->exec("CREATE TABLE IF NOT EXISTS tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT UNIQUE,
        name TEXT NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create document_tags table
    $db->exec("CREATE TABLE IF NOT EXISTS document_tags (
        document_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        document_uuid TEXT,
        tag_uuid TEXT,
        PRIMARY KEY (document_id, tag_id),
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
        FOREIGN KEY (document_uuid) REFERENCES documents(uuid) ON DELETE CASCADE,
        FOREIGN KEY (tag_uuid) REFERENCES tags(uuid) ON DELETE CASCADE
    )");
    
    // Insert default admin user
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $isAdmin = 1;
    $userUuid = generateUUID();
    
    try {
        $stmt = $db->prepare("INSERT INTO users (username, password, is_admin, uuid) VALUES (:username, :password, :is_admin, :uuid)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':is_admin', $isAdmin);
        $stmt->bindParam(':uuid', $userUuid);
        $stmt->execute();
    } catch (PDOException $e) {
        // Ignore if admin user already exists
        if (strpos($e->getMessage(), 'UNIQUE constraint failed: users.username') === false) {
            throw $e;
        }
    }
} 