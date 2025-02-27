<?php
/**
 * Change Password
 * 
 * Allows administrators to change their password
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'error';
    } else {
        // Get form data
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate form data
        if (empty($currentPassword)) {
            $message = 'Current password is required.';
            $messageType = 'error';
        } elseif (empty($newPassword)) {
            $message = 'New password is required.';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 8) {
            $message = 'New password must be at least 8 characters.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match.';
            $messageType = 'error';
        } else {
            // Get database connection
            $db = getDbConnection();
            
            try {
                // Get current user
                $userId = $_SESSION['user_id'];
                $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
                $stmt->bindParam(':id', $userId);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    $message = 'User not found.';
                    $messageType = 'error';
                } elseif (!password_verify($currentPassword, $user['password'])) {
                    $message = 'Current password is incorrect.';
                    $messageType = 'error';
                } else {
                    // Begin transaction
                    $db->beginTransaction();
                    
                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = :password, updated_at = :updated_at WHERE id = :id");
                    $stmt->execute([
                        ':password' => $hashedPassword,
                        ':updated_at' => date('Y-m-d H:i:s'),
                        ':id' => $_SESSION['user_id']
                    ]);
                    
                    // Log the password change activity
                    logActivity('change_password', 'user', $_SESSION['user_id'], [
                        'success' => true
                    ]);
                    
                    // Set success message
                    $_SESSION['flash_message'] = 'Password changed successfully.';
                    $_SESSION['flash_type'] = 'success';
                    
                    // Commit transaction
                    $db->commit();
                }
            } catch (Exception $e) {
                // Rollback transaction
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                
                $message = 'Error changing password: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Include header
$pageTitle = 'Change Password';
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Change Password</h1>
    
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-4">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="current_password">
                        Current Password
                    </label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="new_password">
                        New Password
                    </label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                        Confirm New Password
                    </label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>
            <div class="px-4 py-3 bg-gray-50 text-right">
                <button type="submit" name="change_password" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Change Password
                </button>
            </div>
        </div>
    </form>
</div>

<?php include_once 'footer.php'; ?>
