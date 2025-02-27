<?php
/**
 * Reset Database
 * 
 * Resets the database to default settings
 * WARNING: This will delete all data!
 */

// Include required files
require_once '../includes/init.php';

// Check if user is logged in and is admin
checkAdminAuth();

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_database'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token. Please try again.';
        $messageType = 'error';
    } else {
        // Confirm reset
        $confirm = $_POST['confirm'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($confirm !== 'RESET') {
            $message = 'Please type RESET to confirm database reset.';
            $messageType = 'error';
        } elseif (empty($password)) {
            $message = 'Please enter your password to confirm database reset.';
            $messageType = 'error';
        } else {
            // Verify password
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT password FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password'])) {
                $message = 'Invalid password. Please try again.';
                $messageType = 'error';
            } else {
                try {
                    // Delete database file
                    $dbFile = DB_PATH;
                    
                    if (file_exists($dbFile)) {
                        unlink($dbFile);
                    }
                    
                    // Reinitialize database
                    $db = getDbConnection();
                    
                    // Reset database
                    resetDatabase();
                    
                    // Log the database reset
                    logActivity('reset_database', 'system', 0, [
                        'user_id' => $_SESSION['user_id'],
                        'username' => $_SESSION['username']
                    ]);
                    
                    // Redirect to login page
                    $_SESSION = [];
                    session_destroy();
                    header('Location: login.php?reset=1');
                    exit;
                    
                    $message = 'Database reset successfully. Default settings have been restored.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error resetting database: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
    }
}

// Include header
$pageTitle = 'Reset Database';
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Reset Database</h1>
    
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold">Warning!</p>
        <p>This will delete all data and reset the database to default settings. This action cannot be undone.</p>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="" onsubmit="return confirm('Are you sure you want to reset the database? All data will be lost!');">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-4">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm">
                        Type RESET to confirm
                    </label>
                    <input type="text" 
                           id="confirm" 
                           name="confirm" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                        Enter your password
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>
            <div class="px-4 py-3 bg-gray-50 text-right">
                <button type="submit" name="reset_database" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Reset Database
                </button>
            </div>
        </div>
    </form>
</div>

<?php include_once 'footer.php'; ?>
