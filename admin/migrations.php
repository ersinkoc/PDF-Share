<?php
/**
 * Admin Migrations Page
 * 
 * This page allows administrators to view and run database migrations.
 */

// Include necessary files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/init.php';

// Check if auth.php exists before requiring it
if (file_exists(__DIR__ . '/../includes/auth.php')) {
    require_once __DIR__ . '/../includes/auth.php';
} else {
    // Define minimal authentication functions if auth.php doesn't exist
    if (!function_exists('isLoggedIn')) {
        function isLoggedIn() {
            return true; // Assume logged in for CLI
        }
    }
    
    if (!function_exists('isAdmin')) {
        function isAdmin() {
            return true; // Assume admin for CLI
        }
    }
}

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    // Only check auth when running in web context
    if (isset($_SERVER['HTTP_HOST'])) {
        header('Location: ../login.php');
        exit;
    }
}

// Set page title
$pageTitle = 'Database Migrations';

// Handle manual migration run if requested
$migrationRun = false;
$migrationSuccess = false;
$migrationMessage = '';

if (isset($_POST['run_migrations']) && $_POST['run_migrations'] === '1') {
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $migrationMessage = 'Invalid CSRF token. Please try again.';
    } else {
        // Run migrations
        $migrationRun = true;
        $migrationSuccess = runMigrations();
        
        if ($migrationSuccess) {
            $migrationMessage = 'Migrations applied successfully.';
        } else {
            $migrationMessage = 'No pending migrations to apply or an error occurred.';
        }
    }
}

// Get applied migrations
$db = getDbConnection();
$appliedMigrations = [];
try {
    // Check if migrations table exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
    $tableExists = $result->fetchColumn();
    
    if ($tableExists) {
        $stmt = $db->query("SELECT migration_name, applied_at FROM migrations ORDER BY id DESC");
        $appliedMigrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    logError('Error fetching migrations: ' . $e->getMessage());
}

// Get available migrations
$availableMigrations = getAvailableMigrations();

// Include header
include_once 'header.php';
?>

<div class="bg-white shadow-md rounded-lg p-6 mb-6">
    <h2 class="text-2xl font-bold mb-4">Database Migrations</h2>
    
    <?php if ($migrationRun): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $migrationSuccess ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
            <?php echo htmlspecialchars($migrationMessage); ?>
        </div>
    <?php endif; ?>
    
    <div class="mb-6">
        <p class="mb-4">Database migrations help manage changes to the database schema over time. They ensure that all installations of the application have consistent database structures.</p>
        
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="run_migrations" value="1">
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Run Migrations
            </button>
        </form>
    </div>
    
    <div class="mb-6">
        <h3 class="text-xl font-bold mb-3">Applied Migrations</h3>
        <?php if (empty($appliedMigrations)): ?>
            <p class="text-gray-600 italic">No migrations have been applied yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Migration Name</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Applied At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appliedMigrations as $migration): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($migration['migration_name']); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($migration['applied_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div>
        <h3 class="text-xl font-bold mb-3">Available Migrations</h3>
        <?php if (empty($availableMigrations)): ?>
            <p class="text-gray-600 italic">No available migrations found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Migration Name</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availableMigrations as $migration): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($migration); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <?php 
                                    $isApplied = false;
                                    foreach ($appliedMigrations as $appliedMigration) {
                                        if ($appliedMigration['migration_name'] === $migration) {
                                            $isApplied = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($isApplied): 
                                    ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Applied
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once 'footer.php';
?>
