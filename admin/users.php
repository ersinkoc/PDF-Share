<?php
/**
 * Admin Users
 * 
 * Manage users in the system
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

// Get database connection
$db = getDbConnection();

// Process form submission for adding a new user
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        if (isset($_POST['add_user'])) {
            // Get form data
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
            
            // Validate form data
            if (empty($username) || empty($email) || empty($password)) {
                $message = 'All fields are required.';
                $messageType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Invalid email format.';
                $messageType = 'error';
            } elseif ($password !== $confirmPassword) {
                $message = 'Passwords do not match.';
                $messageType = 'error';
            } else {
                // Check if username or email already exists
                $checkStmt = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
                $checkStmt->bindParam(':username', $username);
                $checkStmt->bindParam(':email', $email);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $message = 'Username or email already exists.';
                    $messageType = 'error';
                } else {
                    // Add new user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $uuid = generateUUID();
                    $insertStmt = $db->prepare("INSERT INTO users (username, email, password, is_admin, uuid, created_at) VALUES (:username, :email, :password, :is_admin, :uuid, CURRENT_TIMESTAMP)");
                    $insertStmt->bindParam(':username', $username);
                    $insertStmt->bindParam(':email', $email);
                    $insertStmt->bindParam(':password', $hashedPassword);
                    $insertStmt->bindParam(':is_admin', $isAdmin, PDO::PARAM_INT);
                    $insertStmt->bindParam(':uuid', $uuid);
                    
                    if ($insertStmt->execute()) {
                        $message = 'User added successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to add user.';
                        $messageType = 'error';
                    }
                }
            }
        } elseif (isset($_POST['delete_user'])) {
            // Delete user
            $userId = (int)$_POST['user_id'];
            
            // Check if user is trying to delete themselves
            if ($userId === (int)$_SESSION['user_id']) {
                $message = 'You cannot delete your own account.';
                $messageType = 'error';
            } else {
                $deleteStmt = $db->prepare("DELETE FROM users WHERE id = :id");
                $deleteStmt->bindParam(':id', $userId, PDO::PARAM_INT);
                
                if ($deleteStmt->execute()) {
                    $message = 'User deleted successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete user.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get all users
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Page title
$pageTitle = 'Users';
?>

<?php include 'header.php'; ?>

<div class="container px-6 py-8 mx-auto">
    <h1 class="text-2xl font-semibold text-gray-800">Users</h1>
    <p class="mt-2 text-gray-600">Manage users in the system</p>
    
    <?php if (!empty($message)): ?>
        <div class="mt-4 p-4 rounded <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Add User Form -->
    <div class="mt-6 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-medium text-gray-800 mb-4">Add New User</h2>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="username" class="block text-gray-700 font-medium mb-2">Username</label>
                    <input type="text" id="username" name="username" 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
                    <input type="email" id="email" name="email" 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                    <input type="password" id="password" name="password" 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
            </div>
            
            <div class="mt-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="is_admin" class="form-checkbox h-5 w-5 text-blue-600">
                    <span class="ml-2 text-gray-700">Admin User</span>
                </label>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="submit" name="add_user" class="px-6 py-2 bg-blue-500 text-white font-medium rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Add User
                </button>
            </div>
        </form>
    </div>
    
    <!-- Users Table -->
    <div class="mt-6 bg-white rounded-lg shadow-md overflow-hidden">
        <h2 class="text-lg font-medium text-gray-800 p-6 border-b">User List</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ((int)$user['id'] === (int)$_SESSION['user_id']): ?>
                                        <span class="ml-2 px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">You</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['is_admin'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-gray-400">Cannot Delete</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                No users found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
