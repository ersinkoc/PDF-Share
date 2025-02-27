<?php
/**
 * Admin Login
 * 
 * Handles admin authentication
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/utilities.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            // Attempt to authenticate
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT id, username, password, is_admin FROM users WHERE username = :username LIMIT 1");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = true;
                $_SESSION['last_activity'] = time();
                
                // Log the login activity
                logActivity('login', 'user', $user['id'], [
                    'username' => $user['username'],
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                // Update last login time
                $updateStmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
                $updateStmt->bindParam(':id', $user['id']);
                $updateStmt->execute();
                
                // Redirect to admin dashboard
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
                // Log failed login attempt
                logError('Failed login attempt for username: ' . $username);
            }
        }
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Get page title
$pageTitle = 'Admin Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - PDF QR Link</title>
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">
        @tailwind base;
        @tailwind components;
        @tailwind utilities;
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">PDF QR Link</h1>
            <p class="text-gray-600">Admin Login</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                <input type="text" id="username" name="username" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Sign In
                </button>
                <a href="../index.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                    Back to Site
                </a>
            </div>
        </form>
    </div>
    
</body>
</html>
