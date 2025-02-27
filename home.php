<?php
/**
 * Home Page
 * 
 * Main landing page for the application
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/utilities.php';

// Get site settings
$db = getDbConnection();
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE is_public = 1");
$settings = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Page title
$pageTitle = $settings['general.site_title'] ?? 'PDF QR Link';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($settings['general.site_description'] ?? 'Modern PDF Sharing Platform'); ?>">
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
                <a href="admin/login.php" class="text-blue-500 hover:text-blue-700">
                    <i class="bi bi-person-circle mr-1"></i> Admin Login
                </a>
            </div>
        </div>
    </header>
    
    <!-- Hero Section -->
    <section class="py-12 bg-blue-600 text-white">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-4xl font-bold mb-4"><?php echo getSettingValue('general.site_description_short'); ?></h2>
            <p class="text-xl mb-8"><?php echo getSettingValue('general.site_description'); ?></p>
            <div class="flex justify-center">
                <a href="admin/login.php" class="bg-white text-blue-600 font-bold py-3 px-6 rounded-lg shadow-lg hover:bg-gray-100 transition duration-300">
                    Get Started
                </a>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Key Features</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-6 bg-gray-50 rounded-lg shadow-md">
                    <div class="text-4xl text-blue-500 mb-4">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">PDF Management</h3>
                    <p class="text-gray-600">Upload, organize, and manage your PDF documents in one place.</p>
                </div>
                
                <div class="p-6 bg-gray-50 rounded-lg shadow-md">
                    <div class="text-4xl text-blue-500 mb-4">
                        <i class="bi bi-qr-code"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">QR Code Generation</h3>
                    <p class="text-gray-600">Automatically generate QR codes for easy document sharing.</p>
                </div>
                
                <div class="p-6 bg-gray-50 rounded-lg shadow-md">
                    <div class="text-4xl text-blue-500 mb-4">
                        <i class="bi bi-link-45deg"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Short URLs</h3>
                    <p class="text-gray-600">Create memorable short URLs for your documents.</p>
                </div>
                
                <div class="p-6 bg-gray-50 rounded-lg shadow-md">
                    <div class="text-4xl text-blue-500 mb-4">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">View Tracking</h3>
                    <p class="text-gray-600">Track document views and downloads with detailed statistics.</p>
                </div>
                
                <div class="p-6 bg-gray-50 rounded-lg shadow-md">
                    <div class="text-4xl text-blue-500 mb-4">
                        <i class="bi bi-phone"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Mobile Optimized</h3>
                    <p class="text-gray-600">Responsive design for both desktop and mobile devices.</p>
                </div>
                
                <div class="p-6 bg-gray-50 rounded-lg shadow-md">
                    <div class="text-4xl text-blue-500 mb-4">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Secure Access</h3>
                    <p class="text-gray-600">Control who can access your documents with public/private settings.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- How It Works Section -->
    <section class="py-16 bg-gray-100">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">How It Works</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-500 text-white rounded-full text-2xl font-bold mb-4">1</div>
                    <h3 class="text-xl font-bold mb-2">Upload Your PDF</h3>
                    <p class="text-gray-600">Upload your PDF document through the admin interface.</p>
                </div>
                
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-500 text-white rounded-full text-2xl font-bold mb-4">2</div>
                    <h3 class="text-xl font-bold mb-2">Get Your Links</h3>
                    <p class="text-gray-600">Receive a short URL and QR code for your document.</p>
                </div>
                
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-500 text-white rounded-full text-2xl font-bold mb-4">3</div>
                    <h3 class="text-xl font-bold mb-2">Share & Track</h3>
                    <p class="text-gray-600">Share your links and track document views and downloads.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h3 class="text-xl font-bold"><?php echo htmlspecialchars($pageTitle); ?></h3>
                    <p class="text-gray-400">Modern PDF Sharing Platform</p>
                </div>
                
                <div class="flex space-x-4">
                    <a href="admin/login.php" class="text-gray-400 hover:text-white">Admin Login</a>
                </div>
            </div>
            
            <div class="mt-8 pt-8 border-t border-gray-700 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($pageTitle); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
