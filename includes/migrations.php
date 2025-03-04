<?php
/**
 * Database Migrations System
 * 
 * This file handles database schema migrations to track and apply database changes
 */

// Include necessary files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Constants for migration system
define('MIGRATION_TABLE', 'migrations');
define('MIGRATION_STATUS_SUCCESS', true);
define('MIGRATION_STATUS_FAILED', false);
define('DEFAULT_ITEMS_PER_PAGE', 10);
define('MIGRATIONS_DIR', __DIR__ . '/migrations');

// Error messages
define('ERR_MIGRATION_FAILED', 'Migration failed: %s');
define('ERR_MIGRATION_FUNCTION_NOT_FOUND', 'Migration function %s does not exist');
define('ERR_MIGRATION_LOG_FAILED', 'Failed to log migration: %s');

/**
 * Migration Manager Class
 */
class MigrationManager {
    /** @var PDO */
    private $db;
    
    /** @var array */
    private $appliedMigrations;
    
    /** @var array */
    private $availableMigrations;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
        $this->appliedMigrations = [];
        $this->availableMigrations = [];
    }
    
    /**
     * Initialize migration system
     * 
     * @return bool
     */
    public function initialize() {
        try {
            $this->createMigrationsTable();
            $this->appliedMigrations = $this->getAppliedMigrations();
            $this->availableMigrations = $this->loadAvailableMigrations();
            return true;
        } catch (Exception $e) {
            $this->logError("Initialization failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Load available migrations from migrations directory
     * 
     * @return array
     */
    private function loadAvailableMigrations() {
        $migrations = [];
        $files = glob(MIGRATIONS_DIR . '/*.php');
        
        // More robust approach for sorting files
        usort($files, function($a, $b) {
            // Extract numeric prefixes from filenames
            preg_match('/^(\d+)_/', basename($a), $a_matches);
            preg_match('/^(\d+)_/', basename($b), $b_matches);
            
            $a_num = isset($a_matches[1]) ? (int)$a_matches[1] : PHP_INT_MAX;
            $b_num = isset($b_matches[1]) ? (int)$b_matches[1] : PHP_INT_MAX;
            
            return $a_num - $b_num;
        });
        
        foreach ($files as $file) {
            require_once $file;
            $migrationName = basename($file, '.php');
            
            // Process migration name more securely
            if (preg_match('/^\d+_(.+)$/', $migrationName, $matches)) {
                $migrations[] = $matches[1];
            }
        }
        
        return $migrations;
    }
    
    /**
     * Run pending migrations
     * 
     * @return bool
     */
    public function run() {
        try {
            $pendingMigrations = $this->getPendingMigrations();
            
            if (empty($pendingMigrations)) {
                /*if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    $this->log("No pending migrations found");
                }*/
                return MIGRATION_STATUS_SUCCESS;
            }
            
            $result = $this->applyMigrations($pendingMigrations);
            $this->log(sprintf("Applied %d migrations", count($pendingMigrations)));
            
            return $result;
        } catch (Exception $e) {
            $this->logError(sprintf(ERR_MIGRATION_FAILED, $e->getMessage()));
            return MIGRATION_STATUS_FAILED;
        }
    }
    
    /**
     * Create migrations table
     */
    private function createMigrationsTable() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS " . MIGRATION_TABLE . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration_name TEXT NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    /**
     * Get applied migrations
     * 
     * @return array
     */
    private function getAppliedMigrations() {
        $stmt = $this->db->query("SELECT migration_name FROM " . MIGRATION_TABLE . " ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get pending migrations
     * 
     * @return array
     */
    private function getPendingMigrations() {
        return array_diff($this->availableMigrations, $this->appliedMigrations);
    }
    
    /**
     * Apply migrations
     * 
     * @param array $pendingMigrations
     * @return bool
     */
    private function applyMigrations($pendingMigrations) {
        // We don't handle transaction management here, each migration will manage its own transaction
        foreach ($pendingMigrations as $migration) {
            try {
                if (!$this->applyMigration($migration)) {
                    $this->logError("Migration failed: " . $migration);
                    return MIGRATION_STATUS_FAILED;
                }
            } catch (Exception $e) {
                $this->logError("Migration exception: " . $e->getMessage());
                return MIGRATION_STATUS_FAILED;
            }
        }
        
        return MIGRATION_STATUS_SUCCESS;
    }
    
    /**
     * Apply single migration
     * 
     * @param string $migration
     * @return bool
     */
    private function applyMigration($migration) {
        $migrationFunction = 'migration_' . $migration;
        
        if (!function_exists($migrationFunction)) {
            $this->logError(sprintf(ERR_MIGRATION_FUNCTION_NOT_FOUND, $migrationFunction));
            return false;
        }
        
        try {
            $this->db->beginTransaction();
            
            // Call migration function
            call_user_func($migrationFunction, $this->db);
            
            // Create migration record
            $this->recordMigration($migration);
            
            // Update settings table (if exists)
            $this->safelyUpdateLastMigration($migration);
            
            $this->db->commit();
            $this->log("Applied migration: $migration");
            
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            // Check special cases
            if ($migration === 'create_settings_table') {
                return true; // Skip update if settings table is being created
            }

            // Check if settings table exists
            $tableExists = $this->db->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='settings'"
            )->fetchColumn();

            if (!$tableExists) {
                return true; // Skip update if table doesn't exist
            }

            // Check if setting exists
            $settingExists = $this->db->query(
                "SELECT COUNT(*) FROM settings WHERE setting_key = 'system.last_migration'"
            )->fetchColumn();

            if ($settingExists) {
                // If error occurs, just log it, no need to stop the migration
                $this->log("Could not update last_migration setting: " . $e->getMessage());
            }
            
            return false;
        }
    }
    
    /**
     * Safely update last migration in settings
     * 
     * @param string $migration
     */
    private function safelyUpdateLastMigration($migration) {
        try {
            if ($migration === 'create_settings_table') {
                return; // Skip update if settings table is being created
            }

            // Check if settings table exists
            $tableExists = $this->db->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='settings'"
            )->fetchColumn();

            if (!$tableExists) {
                return; // Skip update if table doesn't exist
            }

            // Check if setting exists
            $settingExists = $this->db->query(
                "SELECT COUNT(*) FROM settings WHERE setting_key = 'system.last_migration'"
            )->fetchColumn();

            if ($settingExists) {
                // Update
                $stmt = $this->db->prepare(
                    "UPDATE settings SET setting_value = :migration WHERE setting_key = 'system.last_migration'"
                );
            } else {
                // Create
                $stmt = $this->db->prepare(
                    "INSERT INTO settings (setting_key, setting_value) VALUES ('system.last_migration', :migration)"
                );
            }

            $stmt->bindParam(':migration', $migration);
            $stmt->execute();
        } catch (Exception $e) {
            // If error occurs, just log it, no need to stop the migration
            $this->log("Could not update last_migration setting: " . $e->getMessage());
        }
    }
    
    /**
     * Record migration in migrations table
     * 
     * @param string $migration
     */
    private function recordMigration($migration) {
        $stmt = $this->db->prepare("INSERT INTO " . MIGRATION_TABLE . " (migration_name, applied_at) VALUES (:name, datetime('now'))");
        $stmt->bindParam(':name', $migration);
        $stmt->execute();
    }
    
    /**
     * Check if audit log table exists
     * 
     * @return bool
     */
    private function isAuditLogTableExists() {
        try {
            return (bool) $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='audit_log'")->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Log message
     * 
     * @param string $message
     * @param bool $force Force logging even if not in debug mode
     */
    private function log($message, $force = false) {
        // Sadece debug modunda veya force=true ise logla
        if ((defined('DEBUG_MODE') && DEBUG_MODE) || $force) {
            error_log("[Migration] " . $message);
            
            // Only attempt to log to audit_log if the table exists
            if ($this->isAuditLogTableExists()) {
                try {
                    logActivity('MIGRATION', 'system', 'migration', ['message' => $message]);
                } catch (Exception $e) {
                    error_log(sprintf(ERR_MIGRATION_LOG_FAILED, $e->getMessage()));
                }
            }
        }
    }
    
    /**
     * Log error message
     * 
     * @param string $message
     */
    private function logError($message) {
        error_log("[Migration Error] " . $message);
        
        // Only attempt to log to audit_log if the table exists
        if ($this->isAuditLogTableExists()) {
            try {
                logActivity('MIGRATION_ERROR', 'system', 'migration', ['error' => $message]);
            } catch (Exception $e) {
                error_log(sprintf(ERR_MIGRATION_LOG_FAILED, $e->getMessage()));
            }
        }
    }

    /**
     * Get all available migrations
     * 
     * @return array
     */
    public function getAvailableMigrations() {
        return $this->availableMigrations;
    }

    /**
     * Get migration status information
     * 
     * @return array
     */
    public function getMigrationStatus() {
        return [
            'total_migrations' => count($this->availableMigrations),
            'applied_migrations' => count($this->appliedMigrations),
            'pending_migrations' => count($this->getPendingMigrations()),
            'last_migration' => end($this->appliedMigrations) ?: 'None',
            'available_migrations' => $this->availableMigrations,
            'applied_migration_list' => $this->appliedMigrations
        ];
    }
}

/**
 * Run database migrations
 * 
 * @return bool True if migrations were successful
 */
function runMigrations() {
    try {
        $db = getDbConnection();
        $migrationManager = new MigrationManager($db);
        
        if (!$migrationManager->initialize()) {
            return MIGRATION_STATUS_FAILED;
        }
        
        return $migrationManager->run();
    } catch (Exception $e) {
        error_log(sprintf(ERR_MIGRATION_FAILED, $e->getMessage()));
        return MIGRATION_STATUS_FAILED;
    }
}

/**
 * Get available migrations
 * 
 * @return array
 */
function getAvailableMigrations() {
    try {
        $db = getDbConnection();
        $migrationManager = new MigrationManager($db);
        $migrationManager->initialize();
        return $migrationManager->getAvailableMigrations();
    } catch (Exception $e) {
        error_log(sprintf(ERR_MIGRATION_FAILED, $e->getMessage()));
        return [];
    }
}

/**
 * Get detailed migration status
 * 
 * @return array
 */
function getMigrationStatus() {
    try {
        $db = getDbConnection();
        $migrationManager = new MigrationManager($db);
        $migrationManager->initialize();
        return $migrationManager->getMigrationStatus();
    } catch (Exception $e) {
        error_log(sprintf(ERR_MIGRATION_FAILED, $e->getMessage()));
        return [
            'total_migrations' => 0,
            'applied_migrations' => 0,
            'pending_migrations' => 0,
            'last_migration' => 'Error',
            'available_migrations' => [],
            'applied_migration_list' => [],
            'error' => $e->getMessage()
        ];
    }
}
