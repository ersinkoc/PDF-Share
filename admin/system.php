<?php
/**
 * Admin System Variables Page
 * 
 * This page allows administrators to view system variables.
 */

// Include necessary files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/migrations.php';

// Try to include auth.php, but provide fallback for CLI
$authIncluded = false;
if (file_exists(__DIR__ . '/../includes/auth.php')) {
    require_once __DIR__ . '/../includes/auth.php';
    $authIncluded = true;
}

// Check if user is logged in and is admin
// Skip authentication check if running from CLI or auth.php wasn't included
$isCLI = (php_sapi_name() === 'cli');
if (!$isCLI && $authIncluded && (!isLoggedIn() || !isAdmin())) {
    header('Location: ../login.php');
    exit;
}

// Set page title
$pageTitle = 'System Variables';

// Get all system variables
$systemVariables = getAllSystemVariables();

// Include header
include_once 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">System Variables</h1>
        <div>
            <a href="system_status.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                View System Status
            </a>
        </div>
    </div>
    
    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <div class="mb-4 p-4 rounded bg-yellow-100 text-yellow-800">
            <p class="font-medium"> System Variables - Read Only</p>
            <p>System variables are internal values used by the application and are managed automatically. These values should not be modified manually as they could affect system functionality and stability.</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="py-2 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Key</th>
                        <th class="py-2 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Value</th>
                        <th class="py-2 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Description</th>
                        <th class="py-2 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($systemVariables)): ?>
                        <tr>
                            <td colspan="4" class="py-4 px-4 border-b border-gray-200 text-center text-gray-500">No system variables found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($systemVariables as $variable): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($variable['variable_key']); ?></div>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <?php 
                                    $value = htmlspecialchars($variable['variable_value']);
                                    
                                    // Format boolean values for better display
                                    if (strtolower($value) === 'true') {
                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">True</span>';
                                    } elseif (strtolower($value) === 'false') {
                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">False</span>';
                                    } elseif (strlen($value) > 100) {
                                        // For long values, show a truncated version with a toggle
                                        echo '<div class="relative">';
                                        echo '<div class="truncate max-w-xs">' . substr($value, 0, 100) . '...</div>';
                                        echo '<button type="button" class="text-blue-500 hover:text-blue-700 text-xs mt-1" onclick="toggleFullValue(this)">Show more</button>';
                                        echo '<div class="hidden absolute z-10 bg-white border p-2 rounded shadow-lg max-w-lg whitespace-pre-wrap">' . $value . '</div>';
                                        echo '</div>';
                                    } else {
                                        echo $value;
                                    }
                                    ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($variable['description'] ?: ''); ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?php echo date('Y-m-d H:i:s', strtotime($variable['updated_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleFullValue(button) {
    const fullValueDiv = button.nextElementSibling;
    if (fullValueDiv.classList.contains('hidden')) {
        fullValueDiv.classList.remove('hidden');
        button.textContent = 'Show less';
    } else {
        fullValueDiv.classList.add('hidden');
        button.textContent = 'Show more';
    }
}
</script>

<?php
// Include footer
include_once 'footer.php';
?>
