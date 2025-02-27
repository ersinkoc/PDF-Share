<?php
/**
 * Database Functions
 * 
 * Functions for database connection and operations
 */

// Include configuration
require_once __DIR__ . '/config.php';

/**
 * Get database connection
 * 
 * @return PDO Database connection
 */
function getDbConnection() {
    static $db = null;
    
    if ($db === null) {
        try {
            // Create database directory if it doesn't exist
            if (!file_exists(dirname(DB_PATH))) {
                mkdir(dirname(DB_PATH), 0755, true);
            }
            
            // Connect to SQLite database
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign keys
            $db->exec('PRAGMA foreign_keys = ON');
            
            // Initialize database if it's new
            initializeDatabase($db);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    
    return $db;
}

/**
 * Initialize database with migrations table if it doesn't exist
 * 
 * @param PDO $db Database connection
 */
function initializeDatabase($db) {
    try {
        // Create migrations table if it doesn't exist
        $db->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration_name TEXT NOT NULL UNIQUE,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Check if we need to run migrations
        $stmt = $db->query("SELECT COUNT(*) FROM migrations");
        $count = $stmt->fetchColumn();
        
        // If no migrations have been applied, run migrations
        if ($count == 0) {
            // Include migrations file if not already included
            if (!function_exists('runMigrations')) {
                require_once __DIR__ . '/migrations.php';
            }
            
            // Run migrations
            runMigrations();
        }
    } catch (PDOException $e) {
        error_log("Database initialization error: " . $e->getMessage());
    }
}
