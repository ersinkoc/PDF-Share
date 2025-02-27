<?php
/**
 * Admin Stats
 * 
 * Displays detailed statistics for all documents
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

// Get date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get overall statistics
$overallStats = [
    'total_documents' => 0,
    'total_views' => 0,
    'total_downloads' => 0,
    'avg_views_per_doc' => 0,
    'top_viewed_doc' => null,
    'top_downloaded_doc' => null
];

// Get total documents
$docStmt = $db->query("SELECT COUNT(*) FROM documents");
$overallStats['total_documents'] = $docStmt->fetchColumn();

// Get views and downloads within date range
$statsStmt = $db->prepare("
    SELECT 
        SUM(v.count) as total_views,
        SUM(s.downloads) as total_downloads
    FROM 
        (SELECT document_uuid, COUNT(*) as count FROM views 
         WHERE DATE(viewed_at) BETWEEN :start_date AND :end_date 
         GROUP BY document_uuid) v
    LEFT JOIN stats s ON v.document_uuid = s.document_uuid
");
$statsStmt->bindParam(':start_date', $startDate);
$statsStmt->bindParam(':end_date', $endDate);
$statsStmt->execute();
$totals = $statsStmt->fetch(PDO::FETCH_ASSOC);

if ($totals) {
    $overallStats['total_views'] = $totals['total_views'] ?: 0;
    $overallStats['total_downloads'] = $totals['total_downloads'] ?: 0;
}

// Calculate average views per document
if ($overallStats['total_documents'] > 0) {
    $overallStats['avg_views_per_doc'] = round($overallStats['total_views'] / $overallStats['total_documents'], 2);
}

// Get top viewed document
$topViewedStmt = $db->prepare("
    SELECT 
        d.id, 
        d.uuid,
        d.title, 
        COUNT(v.id) as view_count 
    FROM 
        documents d
    LEFT JOIN 
        views v ON d.uuid = v.document_uuid
    WHERE 
        DATE(v.viewed_at) BETWEEN :start_date AND :end_date
    GROUP BY 
        d.id, d.uuid
    ORDER BY 
        view_count DESC
    LIMIT 1
");
$topViewedStmt->bindParam(':start_date', $startDate);
$topViewedStmt->bindParam(':end_date', $endDate);
$topViewedStmt->execute();
$overallStats['top_viewed_doc'] = $topViewedStmt->fetch(PDO::FETCH_ASSOC);

// Get top downloaded document
$topDownloadedStmt = $db->prepare("
    SELECT 
        d.id, 
        d.uuid,
        d.title, 
        s.downloads 
    FROM 
        documents d
    LEFT JOIN 
        stats s ON d.uuid = s.document_uuid
    WHERE 
        s.downloads > 0
    ORDER BY 
        s.downloads DESC
    LIMIT 1
");
$topDownloadedStmt->execute();
$overallStats['top_downloaded_doc'] = $topDownloadedStmt->fetch(PDO::FETCH_ASSOC);

// Get document statistics
$docStmt = $db->prepare("
    SELECT 
        d.id,
        d.uuid,
        d.title as title,
        d.created_at as created_at,
        s.views,
        s.downloads,
        (SELECT COUNT(*) FROM views WHERE document_uuid = d.uuid AND DATE(viewed_at) BETWEEN :start_date AND :end_date) as period_views,
        s.last_view_at
    FROM 
        documents d
    LEFT JOIN 
        stats s ON d.uuid = s.document_uuid
    ORDER BY 
        period_views DESC
");
$docStmt->bindParam(':start_date', $startDate);
$docStmt->bindParam(':end_date', $endDate);
$docStmt->execute();
$documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

// Get device type statistics
$deviceStmt = $db->prepare("
    SELECT 
        device_type,
        COUNT(*) as count
    FROM 
        views
    WHERE 
        DATE(viewed_at) BETWEEN :start_date AND :end_date
    GROUP BY 
        device_type
");
$deviceStmt->bindParam(':start_date', $startDate);
$deviceStmt->bindParam(':end_date', $endDate);
$deviceStmt->execute();
$deviceStats = $deviceStmt->fetchAll(PDO::FETCH_ASSOC);

// Format device stats for chart
$deviceLabels = [];
$deviceData = [];
foreach ($deviceStats as $device) {
    $deviceLabels[] = ucfirst($device['device_type']);
    $deviceData[] = $device['count'];
}

// Get daily views for chart
$dailyViewsStmt = $db->prepare("
    SELECT 
        DATE(viewed_at) as date,
        COUNT(*) as count
    FROM 
        views
    WHERE 
        DATE(viewed_at) BETWEEN :start_date AND :end_date
    GROUP BY 
        DATE(viewed_at)
    ORDER BY 
        date
");
$dailyViewsStmt->bindParam(':start_date', $startDate);
$dailyViewsStmt->bindParam(':end_date', $endDate);
$dailyViewsStmt->execute();
$dailyViews = $dailyViewsStmt->fetchAll(PDO::FETCH_ASSOC);

// Format daily views for chart
$viewDates = [];
$viewCounts = [];
foreach ($dailyViews as $day) {
    $viewDates[] = $day['date'];
    $viewCounts[] = $day['count'];
}

// Page title
$pageTitle = 'Statistics';
?>

<?php include 'header.php'; ?>

<div class="container px-6 py-8 mx-auto">
    <h1 class="text-2xl font-semibold text-gray-800">Document Statistics</h1>
    <p class="mt-2 text-gray-600">Detailed analytics for all documents</p>
    
    <!-- Date Range Filter -->
    <div class="mt-6 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-medium text-gray-800 mb-4">Date Range Filter</h2>
        <form method="GET" action="" class="flex flex-wrap items-end space-x-4">
            <div>
                <label for="start_date" class="block text-gray-700 text-sm font-medium mb-2">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" 
                       class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="end_date" class="block text-gray-700 text-sm font-medium mb-2">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" 
                       class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white font-medium rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Apply Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Overall Statistics -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                    <i class="bi bi-file-earmark-text text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Documents</p>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo $overallStats['total_documents']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500">
                    <i class="bi bi-eye text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Views</p>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo $overallStats['total_views']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                    <i class="bi bi-download text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Downloads</p>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo $overallStats['total_downloads']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                    <i class="bi bi-bar-chart text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Avg. Views Per Doc</p>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo $overallStats['avg_views_per_doc']; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Documents -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-medium text-gray-800 mb-4">Most Viewed Document</h2>
            <?php if ($overallStats['top_viewed_doc']): ?>
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                        <i class="bi bi-file-earmark-text text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">
                            <a href="view.php?uuid=<?php echo $overallStats['top_viewed_doc']['uuid']; ?>" class="hover:underline">
                                <?php echo htmlspecialchars($overallStats['top_viewed_doc']['title']); ?>
                            </a>
                        </p>
                        <p class="text-lg font-semibold text-gray-800">
                            <?php echo $overallStats['top_viewed_doc']['view_count']; ?> views
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-gray-600">No data available</p>
            <?php endif; ?>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-medium text-gray-800 mb-4">Most Downloaded Document</h2>
            <?php if ($overallStats['top_downloaded_doc']): ?>
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                        <i class="bi bi-file-earmark-text text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">
                            <a href="view.php?uuid=<?php echo $overallStats['top_downloaded_doc']['uuid']; ?>" class="hover:underline">
                                <?php echo htmlspecialchars($overallStats['top_downloaded_doc']['title']); ?>
                            </a>
                        </p>
                        <p class="text-lg font-semibold text-gray-800">
                            <?php echo $overallStats['top_downloaded_doc']['downloads']; ?> downloads
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-gray-600">No data available</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-medium text-gray-800 mb-4">Daily Views</h2>
            <canvas id="dailyViewsChart" width="400" height="200"></canvas>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-medium text-gray-800 mb-4">Device Types</h2>
            <canvas id="deviceTypeChart" width="400" height="200"></canvas>
        </div>
    </div>
    
    <!-- Document Statistics Table -->
    <div class="mt-6 bg-white rounded-lg shadow-md overflow-hidden">
        <h2 class="text-lg font-medium text-gray-800 p-6 border-b">Document Statistics</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upload Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Views</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period Views</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Downloads</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Viewed</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($documents as $document): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="view.php?uuid=<?php echo $document['uuid']; ?>" class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($document['title']); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($document['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $document['views'] ?: 0; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $document['period_views'] ?: 0; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $document['downloads'] ?: 0; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $document['last_view_at'] ? date('M d, Y H:i', strtotime($document['last_view_at'])) : 'Never'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                No documents found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
    // Daily Views Chart
    const dailyViewsCtx = document.getElementById('dailyViewsChart').getContext('2d');
    const dailyViewsChart = new Chart(dailyViewsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($viewDates); ?>,
            datasets: [{
                label: 'Views',
                data: <?php echo json_encode($viewCounts); ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Device Type Chart
    const deviceTypeCtx = document.getElementById('deviceTypeChart').getContext('2d');
    const deviceTypeChart = new Chart(deviceTypeCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($deviceLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($deviceData); ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(239, 68, 68, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
</script>

<?php include 'footer.php'; ?>
