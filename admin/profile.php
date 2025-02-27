<?php
/**
 * Admin Profile
 * 
 * Allows admin to update their profile information
 */

// Define base path if not defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

// Include required files
require_once BASE_PATH . 'includes/config.php';
require_once BASE_PATH . 'includes/database.php';
require_once BASE_PATH . 'includes/utilities.php';

// Check if user is logged in
checkAdminSession();

// Get current user information
$db = getDbConnection();
$userId = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        // Get form data
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate form data
        if (empty($username) || empty($email)) {
            $message = 'Username and email are required.';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email format.';
            $messageType = 'error';
        } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
            $message = 'New passwords do not match.';
            $messageType = 'error';
        } elseif (!empty($newPassword) && !password_verify($currentPassword, $user['password'])) {
            $message = 'Current password is incorrect.';
            $messageType = 'error';
        } else {
            // Check if username or email already exists
            $checkStmt = $db->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id");
            $checkStmt->bindParam(':username', $username);
            $checkStmt->bindParam(':email', $email);
            $checkStmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $message = 'Username or email already exists.';
                $messageType = 'error';
            } else {
                // Update user information
                if (!empty($newPassword)) {
                    // Update with new password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $db->prepare("UPDATE users SET username = :username, email = :email, password = :password WHERE id = :id");
                    $updateStmt->bindParam(':password', $hashedPassword);
                } else {
                    // Update without changing password
                    $updateStmt = $db->prepare("UPDATE users SET username = :username, email = :email WHERE id = :id");
                }
                
                $updateStmt->bindParam(':username', $username);
                $updateStmt->bindParam(':email', $email);
                $updateStmt->bindParam(':id', $userId, PDO::PARAM_INT);
                
                if ($updateStmt->execute()) {
                    $message = 'Profile updated successfully.';
                    $messageType = 'success';
                    
                    // Update session username
                    $_SESSION['username'] = $username;
                    
                    // Refresh user data
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $message = 'Failed to update profile.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Page title
$pageTitle = 'Profile';
?>

<?php include 'header.php'; ?>

<div class="container px-6 py-8 mx-auto">
    <h1 class="text-2xl font-semibold text-gray-800">Profile</h1>
    <p class="mt-2 text-gray-600">Update your profile information</p>
    
    <?php if (!empty($message)): ?>
        <div class="mt-4 p-4 rounded <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="mt-6 bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="mb-4">
                <label for="username" class="block text-gray-700 font-medium mb-2">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            
            <div class="mb-4">
                <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            
            <div class="mt-8 mb-2">
                <h2 class="text-lg font-medium text-gray-800">Change Password</h2>
                <p class="text-sm text-gray-600">Leave blank to keep your current password</p>
            </div>
            
            <div class="mb-4">
                <label for="current_password" class="block text-gray-700 font-medium mb-2">Current Password</label>
                <input type="password" id="current_password" name="current_password" 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label for="new_password" class="block text-gray-700 font-medium mb-2">New Password</label>
                <input type="password" id="new_password" name="new_password" 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="flex justify-end">
                <button type="submit" name="update_profile" class="px-6 py-2 bg-blue-500 text-white font-medium rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
