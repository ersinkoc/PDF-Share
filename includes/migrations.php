<?php
/**
 * Database Migrations System
 * 
 * This file handles database schema migrations to track and apply database changes
 */

// Include necessary files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Run database migrations
 * 
 * @return bool True if migrations were successful
 */
function runMigrations() {
    try {
        // Get database connection
        $db = getDbConnection();
        
        // Create migrations table if it doesn't exist
        createMigrationsTable($db);
        
        // Get applied migrations
        $appliedMigrations = getAppliedMigrations($db);
        
        // Get available migrations
        $availableMigrations = getAvailableMigrations();
        
        // Determine pending migrations
        $pendingMigrations = array_diff($availableMigrations, $appliedMigrations);
        
        // If no pending migrations, return true
        if (empty($pendingMigrations)) {
            return true;
        }
        
        // Apply pending migrations
        $result = applyMigrations($db, $pendingMigrations);
        
        // Log migration activity
        logMigration('Applied ' . count($pendingMigrations) . ' migrations', $result);
        
        return $result;
    } catch (Exception $e) {
        error_log("Migration error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create migrations table if it doesn't exist
 * 
 * @param PDO $db Database connection
 */
function createMigrationsTable($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration_name TEXT NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

/**
 * Get list of applied migrations
 * 
 * @param PDO $db Database connection
 * @return array List of applied migration names
 */
function getAppliedMigrations($db) {
    $stmt = $db->query("SELECT migration_name FROM migrations ORDER BY id");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get list of available migrations
 * 
 * @return array List of available migrations
 */
function getAvailableMigrations() {
    // Format: 'migration_name' => function($db) { /* migration code */ }
    return [
        'create_tables',
        'create_settings_table',
        'create_audit_log_table',
        'add_user_settings',
        'add_storage_settings',
        'add_system_variables',
        'update_document_table'
    ];
}

/**
 * Apply migrations
 * 
 * @param PDO $db Database connection
 * @param array $pendingMigrations List of pending migrations
 * @return bool True if all migrations were applied successfully, false otherwise
 */
function applyMigrations($db, $pendingMigrations) {
    if (empty($pendingMigrations)) {
        return true;
    }
    
    // Force any existing transaction to complete
    if ($db->inTransaction()) {
        try {
            $db->commit();
        } catch (Exception $e) {
            error_log("Warning: Had to commit an existing transaction before migrations: " . $e->getMessage());
        }
    }
    
    $success = true;
    
    foreach ($pendingMigrations as $migration) {
        // Construct migration function name
        $migrationFunction = 'migration_' . $migration;
        
        // Check if migration function exists
        if (!function_exists($migrationFunction)) {
            error_log("Migration function $migrationFunction does not exist");
            continue;
        }
        
        // Start a new transaction for each migration
        try {
            $db->beginTransaction();
            
            // Apply migration
            call_user_func($migrationFunction, $db);
            
            // Record migration in migrations table
            $stmt = $db->prepare("INSERT INTO migrations (migration_name, applied_at) VALUES (:name, datetime('now'))");
            $stmt->bindParam(':name', $migration);
            $stmt->execute();
            
            // Update last migration in settings if the table exists
            $tableExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'")->fetchColumn();
            if ($tableExists) {
                $stmt = $db->prepare("UPDATE settings SET setting_value = :migration WHERE setting_key = 'system.last_migration'");
                $stmt->bindParam(':migration', $migration);
                $stmt->execute();
            }
            
            // Commit the transaction
            $db->commit();
            
            // Log success
            error_log("Applied migration: $migration");
        } catch (Exception $e) {
            // Rollback the transaction
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log("Migration failed: " . $e->getMessage());
            $success = false;
            
            // Check if this is a UNIQUE constraint error for users table
            if (strpos($e->getMessage(), 'UNIQUE constraint failed: users.username') !== false) {
                // This is a known issue with the admin user already existing
                // We can safely continue with other migrations
                continue;
            }
            
            // For other errors, stop migration process to prevent data corruption
            return false;
        }
    }
    
    return $success;
}

/**
 * Log migration activity
 * 
 * @param string $message Message to log
 */
function logMigration($message) {
    try {
        logActivity('MIGRATION', 'system', 'migration', ['message' => $message]);
    } catch (Exception $e) {
        error_log("Failed to log migration: " . $e->getMessage());
    }
}



/**
 * Migration: Create settings table
 * 
 * @param PDO $db Database connection
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
        ['qrcode.size', '300', 'QR Code Size', 'number', 0, 1],
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO settings 
        (setting_key, setting_value, setting_description, setting_type, is_public, is_editable) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
}

/**
 * Migration: Create audit log table
 * 
 * @param PDO $db Database connection
 */
function migration_create_audit_log_table($db) {
    // Create audit_log table
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

/**
 * Migration: Add user settings
 * 
 * @param PDO $db Database connection
 */
function migration_add_user_settings($db) {
    // Create user_settings table
    $db->exec("CREATE TABLE IF NOT EXISTS user_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        UNIQUE(user_id, setting_key)
    )");
    
    // Add default user settings
    $db->exec("UPDATE settings SET setting_value = 'add_user_settings' WHERE setting_key = 'system.last_migration'");
}

/**
 * Migration: Add storage settings
 * 
 * @param PDO $db Database connection
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


/**
 * Migration: Add system variables
 * 
 * @param PDO $db Database connection
 */
function migration_add_system_variables($db) {
    // Create system_variables table
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

/**
 * Migration: Create initial tables
 * 
 * @param PDO $db Database connection
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
    
    // Check if admin user already exists
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $checkStmt->bindParam(':username', $username);
    $checkStmt->execute();
    
    if ($checkStmt->fetchColumn() == 0) {
        // Only insert if admin doesn't exist
        $stmt = $db->prepare("INSERT INTO users (username, password, is_admin, uuid) VALUES (:username, :password, :is_admin, :uuid)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':is_admin', $isAdmin);
        $stmt->bindParam(':uuid', $userUuid);
        $stmt->execute();
    }
}

/**
 * Migration: Update document table
 * 
 * @param PDO $db Database connection
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
