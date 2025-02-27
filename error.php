<?php
/**
 * Error Page
 * 
 * Displays error information to the user
 */

// Set page title
$pageTitle = 'Error';

// Get error message if available
$errorMessage = $exception->getMessage() ?? 'An unexpected error occurred.';
$errorFile = $exception->getFile() ?? '';
$errorLine = $exception->getLine() ?? '';

// Only show detailed error information to admins
$showDetails = false;
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $showDetails = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
        <div class="text-center">
            <h1 class="text-red-600 text-4xl mb-4">Error</h1>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
            </div>
            
            <?php if ($showDetails): ?>
            <div class="mt-4 text-left bg-gray-100 p-4 rounded text-sm">
                <h2 class="font-bold mb-2">Error Details:</h2>
                <p><strong>File:</strong> <?php echo htmlspecialchars($errorFile); ?></p>
                <p><strong>Line:</strong> <?php echo htmlspecialchars($errorLine); ?></p>
                <?php if (isset($exception) && method_exists($exception, 'getTraceAsString')): ?>
                <div class="mt-2">
                    <strong>Stack Trace:</strong>
                    <pre class="text-xs mt-1 overflow-x-auto"><?php echo htmlspecialchars($exception->getTraceAsString()); ?></pre>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="mt-6">
                <a href="index.php" class="inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Return to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>
