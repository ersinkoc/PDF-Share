<?php
/**
 * Admin Header
 * 
 * Common header for admin pages
 */
// Ensure this file is included, not accessed directly
if (!defined('BASE_URL')) {
    exit('No direct script access allowed');
}

// Include necessary files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/init.php';

// Set default value for currentPage
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Admin'); ?> - <?php echo getSettingValue('general.site_title', 'PDF QR Link'); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css?<?php echo CSS_CACHE_KEY; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" integrity="sha512-dPXYcDub/aeb08c63jRq/k6GaKccl256JQy/AnOq7CAnEZ9FzSL9wSbcZkMp4R26vBsMLFYH4kQ67/bbV8XaCQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://unpkg.com/alpinejs@3.14.8" defer></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div x-data="{ sidebarOpen: false, sidebarPinned: true }" 
         @resize.window="sidebarOpen = (window.innerWidth >= 768) ? sidebarPinned : false">
        <!-- Sidebar Overlay - only visible on mobile when sidebar is open -->
        <div x-show="sidebarOpen && window.innerWidth < 768" 
             @click="sidebarOpen = false" 
             class="fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
        </div>
        
        <!-- Sidebar -->
        <aside class="fixed inset-y-0 left-0 z-30 w-64 bg-gray-800 overflow-y-auto transition-all duration-300 transform"
             :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen, 'md:translate-x-0': sidebarPinned}">
            <div class="flex items-center justify-between h-16 bg-gray-900 px-4">
                <span class="text-white font-bold text-xl"><?php echo getSettingValue('general.site_title', 'PDF QR Link'); ?></span>
                <button @click="sidebarOpen = false" class="text-white focus:outline-none focus:ring md:hidden">
                    <i class="bi bi-x text-xl"></i>
                </button>
            </div>
            <nav class="mt-5 px-2">
                <a href="<?php echo BASE_URL; ?>admin/index.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'index.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-speedometer2 mr-3 text-xl"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/upload.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'upload.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-upload mr-3 text-xl"></i>
                    <span>Upload PDF</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/bulk-upload.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'bulk-upload.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-files mr-3 text-xl"></i>
                    <span>Bulk Upload</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/documents.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'documents.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-file-earmark-pdf mr-3 text-xl"></i>
                    <span>Documents</span>
                </a>
                
                
                <!-- Storage Group -->
                <div class="mt-4 mb-2 px-2">
                    <h3 class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Storage & Backup</h3>
                </div>
                <a href="<?php echo BASE_URL; ?>admin/storage.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'storage.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-hdd mr-3 text-xl"></i>
                    <span>Storage</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/file_manager.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'file_manager.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-file-earmark-pdf mr-3 text-xl"></i>
                    <span>PDF Manager</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/import_export.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'import_export.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-archive mr-3 text-xl"></i>
                    <span>Import & Export</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/db_management.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'db_management.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-database-gear mr-3 text-xl"></i>
                    <span>DB Management</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/pdf_backup.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'pdf_backup.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-file-earmark-pdf mr-3 text-xl"></i>
                    <span>PDF QR Backup</span>
                </a>
                
                <!-- Logs Group -->
                <div class="mt-4 mb-2 px-2">
                    <h3 class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Logs</h3>
                </div>
                <a href="<?php echo BASE_URL; ?>admin/logs.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'logs.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-journal-text mr-3 text-xl"></i>
                    <span>Logs</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/error_logs.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'error_logs.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-exclamation-triangle mr-3 text-xl"></i>
                    <span>Error Logs</span>
                </a>
                
                
                <!-- System Group -->
                <div class="mt-4 mb-2 px-2">
                    <h3 class="text-xs uppercase tracking-wider text-gray-500 font-semibold">System Settings</h3>
                </div>
                <a href="<?php echo BASE_URL; ?>admin/settings.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo $currentPage === 'settings.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-gear mr-3 flex-shrink-0 h-6 w-6"></i>
                    Settings
                </a>
                <a href="<?php echo BASE_URL; ?>admin/system_status.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo $currentPage === 'system_status.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-info-circle mr-3 flex-shrink-0 h-6 w-6"></i>
                    System Status
                </a>
                <a href="<?php echo BASE_URL; ?>admin/system.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo $currentPage === 'system.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-cpu mr-3 flex-shrink-0 h-6 w-6"></i>
                    System Variables
                </a>
                <a href="<?php echo BASE_URL; ?>admin/migrations.php" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md <?php echo $currentPage === 'migrations.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-database-up mr-3 flex-shrink-0 h-6 w-6"></i>
                    Migrations
                </a>

                <!-- Account Group -->
                <div class="mt-4 mb-2 px-2">
                    <h3 class="text-xs uppercase tracking-wider text-gray-500 font-semibold">Account</h3>
                </div>
                <a href="<?php echo BASE_URL; ?>admin/change_password.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'change_password.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-key mr-3 text-xl"></i>
                    <span>Change Password</span>
                </a>

                <a href="<?php echo BASE_URL; ?>admin/logout.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo $currentPage === 'logout.php' ? 'bg-gray-700 text-white' : 'text-white hover:bg-gray-700'; ?>">
                    <i class="bi bi-box-arrow-right mr-3 text-xl"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="flex flex-col min-h-screen transition-all duration-300"
             :class="{'md:ml-64': sidebarPinned, 'ml-0': !sidebarPinned}">
            <!-- Top Navigation -->
            <header class="bg-white shadow relative z-10">
                <div class="flex justify-between items-center px-4 py-3">
                    <div class="flex items-center">
                        <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 focus:outline-none focus:ring mr-3">
                            <i class="bi text-xl" :class="sidebarOpen ? 'bi-x' : 'bi-list'"></i>
                        </button>
                        <button @click="sidebarPinned = !sidebarPinned" class="text-gray-500 focus:outline-none focus:ring mr-3 hidden md:block">
                            <i class="bi text-xl" :class="sidebarPinned ? 'bi-pin-angle-fill' : 'bi-pin-angle'"></i>
                        </button>
                        <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($pageTitle ?? 'Admin'); ?></span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-700 mr-2"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                        <a href="<?php echo BASE_URL; ?>admin/logout.php" class="text-gray-500 hover:text-gray-700">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-grow p-4 relative z-0">
                <!-- Content will be injected here -->
