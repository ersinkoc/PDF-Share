<?php
/**
 * 404 Not Found
 * 
 * Error page for 404 Not Found
 */

// Define base path if not defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);
}

// Include required files
require_once 'includes/config.php';

// Set 404 header if not already set
if (http_response_code() !== 404) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found - PDF QR Link</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8 text-center">
        <div class="text-red-500 text-6xl mb-4">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <h1 class="text-3xl font-bold mb-4">404 Not Found</h1>
        <p class="text-gray-600 mb-6">The page you are looking for does not exist or has been moved.</p>
        <a href="<?php echo BASE_URL; ?>" class="inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
            <i class="bi bi-house-door mr-2"></i> Go to Homepage
        </a>
    </div>
</body>
</html>
