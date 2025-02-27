<?php
/**
 * System Status Page
 * 
 * Displays detailed system status information
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/utilities.php';
require_once '../includes/migrations.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Set page title
$pageTitle = 'System Status';

// Get system status
$systemStatus = getSystemStatus();

// Include header
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">System Status</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Database Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <i class="bi bi-database mr-2 text-blue-500"></i>
                Database Information
            </h2>
            <div class="space-y-3">
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">Database Version:</span>
                    <span class="font-semibold"><?php echo $systemStatus['db_version']; ?></span>
                </div>
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">Database Size:</span>
                    <span class="font-semibold"><?php echo $systemStatus['db_size']; ?></span>
                </div>
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">Last Migration:</span>
                    <span class="font-semibold"><?php echo $systemStatus['last_migration']; ?></span>
                </div>
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">Total Migrations:</span>
                    <span class="font-semibold"><?php echo $systemStatus['total_migrations']; ?></span>
                </div>
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">Applied Migrations:</span>
                    <span class="font-semibold"><?php echo $systemStatus['applied_migrations']; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">Pending Migrations:</span>
                    <span class="font-semibold <?php echo $systemStatus['pending_migrations'] > 0 ? 'text-yellow-500' : 'text-green-500'; ?>">
                        <?php echo $systemStatus['pending_migrations']; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- PHP Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <i class="bi bi-filetype-php mr-2 text-purple-500"></i>
                PHP Information
            </h2>
            <div class="space-y-3">
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">PHP Version:</span>
                    <span class="font-semibold"><?php echo $systemStatus['php_version']; ?></span>
                </div>
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">Memory Limit:</span>
                    <span class="font-semibold"><?php echo $systemStatus['memory_limit']; ?></span>
                </div>
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">Max Upload Size:</span>
                    <span class="font-semibold"><?php echo $systemStatus['max_upload_size']; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">Post Max Size:</span>
                    <span class="font-semibold"><?php echo $systemStatus['post_max_size']; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Server Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <i class="bi bi-server mr-2 text-green-500"></i>
                Server Information
            </h2>
            <div class="space-y-3">
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">Server Software:</span>
                    <span class="font-semibold"><?php echo $systemStatus['server_software']; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">Server Protocol:</span>
                    <span class="font-semibold"><?php echo $systemStatus['server_protocol']; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Application Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <i class="bi bi-app mr-2 text-blue-500"></i>
                Application Information
            </h2>
            <div class="space-y-3">
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">Application Version:</span>
                    <span class="font-semibold"><?php echo $systemStatus['app_version']; ?></span>
                </div>
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="text-gray-700">Installation Date:</span>
                    <span class="font-semibold"><?php echo $systemStatus['installation_date']; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">System Variables:</span>
                    <span class="font-semibold"><?php echo $systemStatus['system_variables_count']; ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Actions Section -->
    <div class="flex flex-wrap gap-4 mb-8">
        <a href="migrations.php" class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition-colors">
            <i class="bi bi-database-up mr-2"></i>
            Manage Migrations
        </a>
        <a href="system.php" class="inline-flex items-center px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-md transition-colors">
            <i class="bi bi-gear mr-2"></i>
            System Variables
        </a>
        <a href="logs.php" class="inline-flex items-center px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-md transition-colors">
            <i class="bi bi-journal-text mr-2"></i>
            View Logs
        </a>
    </div>
    
    <!-- PHP Info Section -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">PHP Information</h2>
            <button id="togglePhpInfo" class="text-blue-500 hover:text-blue-700">
                <i class="bi bi-eye"></i> Show Details
            </button>
        </div>
        <div id="phpInfoContainer" class="hidden">
            <div class="bg-gray-100 p-4 rounded overflow-auto max-h-96">
                <?php
                ob_start();
                phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);
                $phpinfo = ob_get_clean();
                
                // Convert the phpinfo output to be more tailwind-friendly
                $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo);
                $phpinfo = str_replace('<table', '<table class="w-full text-sm"', $phpinfo);
                $phpinfo = str_replace('<tr class="h">', '<tr class="bg-blue-100">', $phpinfo);
                $phpinfo = str_replace('<tr class="v">', '<tr class="border-t">', $phpinfo);
                $phpinfo = str_replace('<td class="e">', '<td class="p-2 font-semibold">', $phpinfo);
                $phpinfo = str_replace('<td class="v">', '<td class="p-2">', $phpinfo);
                
                echo $phpinfo;
                ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('togglePhpInfo');
    const phpInfoContainer = document.getElementById('phpInfoContainer');
    
    toggleBtn.addEventListener('click', function() {
        if (phpInfoContainer.classList.contains('hidden')) {
            phpInfoContainer.classList.remove('hidden');
            toggleBtn.innerHTML = '<i class="bi bi-eye-slash"></i> Hide Details';
        } else {
            phpInfoContainer.classList.add('hidden');
            toggleBtn.innerHTML = '<i class="bi bi-eye"></i> Show Details';
        }
    });
});
</script>

<?php include 'footer.php'; ?>
