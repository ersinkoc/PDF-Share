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
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Database Migrations</h2>
        <form method="post" action="" class="inline-block">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="run_migrations" value="1">
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                Run Migrations
            </button>
        </form>
    </div>
    
    <?php if ($migrationRun): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $migrationSuccess ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-yellow-100 text-yellow-700 border border-yellow-400'; ?>">
            <?php echo htmlspecialchars($migrationMessage); ?>
        </div>
    <?php endif; ?>
    
    <div class="mb-6">
        <p class="text-gray-600 mb-4">Database migrations help manage changes to the database schema over time. They ensure that all installations of the application have consistent database structures.</p>
        
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        Total Migrations: <?php echo count($availableMigrations); ?> | 
                        Applied: <?php echo count($appliedMigrations); ?> | 
                        Pending: <?php echo count($availableMigrations) - count($appliedMigrations); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr>
                    <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Migration Name</th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Applied At</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($availableMigrations as $migration): 
                    $isApplied = false;
                    $appliedDate = '';
                    
                    foreach ($appliedMigrations as $appliedMigration) {
                        if ($appliedMigration['migration_name'] === $migration) {
                            $isApplied = true;
                            $appliedDate = $appliedMigration['applied_at'];
                            break;
                        }
                    }
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 font-medium text-gray-900">
                            <?php echo htmlspecialchars($migration); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5">
                            <?php if ($isApplied): ?>
                                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    <svg class="mr-1.5 h-4 w-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Applied
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    <svg class="mr-1.5 h-4 w-4 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                    </svg>
                                    Pending
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            <?php echo $isApplied ? htmlspecialchars($appliedDate) : '-'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Include footer
include_once 'footer.php';
?>
