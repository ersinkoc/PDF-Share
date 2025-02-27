<?php
/**
 * Admin Dashboard
 * 
 * Main admin interface showing statistics and document management
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/utilities.php';
require_once '../includes/migrations.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get document statistics
$db = getDbConnection();
$totalDocuments = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$totalViews = $db->query("SELECT COUNT(*) FROM views")->fetchColumn() ?: 0;
$totalDownloads = $db->query("SELECT SUM(downloads) FROM stats")->fetchColumn() ?: 0;
$storageUsage = getTotalStorageUsage();


// Get monthly statistics for charts
$monthlyStats = [];
$currentYear = date('Y');
$stmt = $db->prepare("SELECT strftime('%m', created_at) as month, COUNT(*) as count FROM documents WHERE strftime('%Y', created_at) = ? GROUP BY month ORDER BY month");
$stmt->execute([$currentYear]);
$monthlyUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to array format for Chart.js
$uploadMonths = [];
$uploadCounts = [];
foreach ($monthlyUploads as $data) {
    $monthName = date('M', mktime(0, 0, 0, $data['month'], 10));
    $uploadMonths[] = $monthName;
    $uploadCounts[] = $data['count'];
}

// Get monthly views
$stmt = $db->prepare("SELECT strftime('%m', viewed_at) as month, COUNT(*) as count FROM views WHERE strftime('%Y', viewed_at) = ? GROUP BY month ORDER BY month");
$stmt->execute([$currentYear]);
$monthlyViews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to array format for Chart.js
$viewMonths = [];
$viewCounts = [];
foreach ($monthlyViews as $data) {
    $monthName = date('M', mktime(0, 0, 0, $data['month'], 10));
    $viewMonths[] = $monthName;
    $viewCounts[] = $data['count'];
}

// Get top 5 documents by views
$topDocuments = $db->query("SELECT d.title, COUNT(v.id) as view_count 
                           FROM documents d 
                           JOIN views v ON d.uuid = v.document_uuid 
                           GROUP BY d.uuid 
                           ORDER BY view_count DESC 
                           LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$topDocumentLabels = [];
$topDocumentViews = [];
foreach ($topDocuments as $doc) {
    $topDocumentLabels[] = substr($doc['title'], 0, 20) . (strlen($doc['title']) > 20 ? '...' : '');
    $topDocumentViews[] = $doc['view_count'];
}

$recentDocuments = $db->query("SELECT d.id, d.uuid, d.title, d.short_url, d.created_at, d.file_size,
                              (SELECT COUNT(*) FROM views WHERE document_uuid = d.uuid) as views, 
                              COALESCE(s.downloads, 0) as downloads 
                              FROM documents d 
                              LEFT JOIN stats s ON d.uuid = s.document_uuid 
                              ORDER BY d.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get page title
$pageTitle = 'Admin Dashboard';
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Admin Dashboard</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Stats Cards -->
        <div class="bg-white rounded-lg shadow p-6 transform transition-transform duration-300 hover:scale-105">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Documents</h3>
                <i class="bi bi-file-earmark-pdf text-3xl text-blue-500"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $totalDocuments; ?></p>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                <div class="bg-blue-600 h-2.5 rounded-full" style="width: 100%"></div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6 transform transition-transform duration-300 hover:scale-105">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Views</h3>
                <i class="bi bi-eye text-3xl text-green-500"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $totalViews; ?></p>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                <div class="bg-green-600 h-2.5 rounded-full" style="width: <?php echo min(100, ($totalViews / max(1, $totalDocuments * 10)) * 100); ?>%"></div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6 transform transition-transform duration-300 hover:scale-105">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Downloads</h3>
                <i class="bi bi-download text-3xl text-purple-500"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $totalDownloads; ?></p>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                <div class="bg-purple-600 h-2.5 rounded-full" style="width: <?php echo min(100, ($totalDownloads / max(1, $totalDocuments * 5)) * 100); ?>%"></div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6 transform transition-transform duration-300 hover:scale-105">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Storage Usage</h3>
                <i class="bi bi-hdd text-3xl text-<?php echo $storageUsage['percent'] > 80 ? 'red' : ($storageUsage['percent'] > 60 ? 'yellow' : 'blue'); ?>-500"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $storageUsage['used_formatted']; ?> / <?php echo $storageUsage['total_formatted']; ?></p>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                <?php 
                $percentUsed = $storageUsage['percent'];
                $barColor = $percentUsed > 80 ? 'bg-red-600' : ($percentUsed > 60 ? 'bg-yellow-600' : 'bg-blue-600');
                ?>
                <div class="<?php echo $barColor; ?> h-2.5 rounded-full" style="width: <?php echo $percentUsed; ?>%"></div>
            </div>
        </div>
    </div>
   
    
    <!-- Charts Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Monthly Uploads</h2>
            <canvas id="uploadsChart" height="300"></canvas>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Monthly Views</h2>
            <canvas id="viewsChart" height="300"></canvas>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Top Documents by Views</h2>
            <canvas id="topDocumentsChart" height="300"></canvas>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Storage Distribution</h2>
            <canvas id="storageChart" height="300"></canvas>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Recent Documents</h2>
            <a href="documents.php" class="text-blue-500 hover:text-blue-700">View All</a>
        </div>
        
        <?php if (count($recentDocuments) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b text-left">Title</th>
                            <th class="py-2 px-4 border-b text-left">Short URL</th>
                            <th class="py-2 px-4 border-b text-left">Uploaded</th>
                            <th class="py-2 px-4 border-b text-left">File Size</th>
                            <th class="py-2 px-4 border-b text-left">Views</th>
                            <th class="py-2 px-4 border-b text-left">Downloads</th>
                            <th class="py-2 px-4 border-b text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDocuments as $doc): ?>
                            <tr>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($doc['title']); ?></td>
                                <td class="py-2 px-4 border-b">
                                    <a href="<?php echo BASE_URL . 's/' . $doc['short_url']; ?>" class="text-blue-500 hover:text-blue-700" target="_blank">
                                        <?php echo 's/' . $doc['short_url']; ?>
                                    </a>
                                </td>
                                <td class="py-2 px-4 border-b"><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo formatBytes($doc['file_size']); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo $doc['views']; ?></td>
                                <td class="py-2 px-4 border-b"><?php echo $doc['downloads']; ?></td>
                                <td class="py-2 px-4 border-b">
                                    <a href="view.php?uuid=<?php echo $doc['uuid']; ?>" class="text-blue-500 hover:text-blue-700 mr-2">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?uuid=<?php echo $doc['uuid']; ?>" class="text-green-500 hover:text-green-700 mr-2">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500">No documents uploaded yet.</p>
        <?php endif; ?>
    </div>
    
    <div class="flex justify-between items-center mb-8">
        <a href="upload.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="bi bi-upload mr-2"></i> Upload New Document
        </a>
        <a href="settings.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
            <i class="bi bi-gear mr-2"></i> Settings
        </a>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Uploads Chart
    const uploadsCtx = document.getElementById('uploadsChart').getContext('2d');
    const uploadsChart = new Chart(uploadsCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($uploadMonths); ?>,
            datasets: [{
                label: 'Uploads',
                data: <?php echo json_encode($uploadCounts); ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Document Uploads by Month'
                }
            },
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
    
    // Monthly Views Chart
    const viewsCtx = document.getElementById('viewsChart').getContext('2d');
    const viewsChart = new Chart(viewsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($viewMonths); ?>,
            datasets: [{
                label: 'Views',
                data: <?php echo json_encode($viewCounts); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Document Views by Month'
                }
            },
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
    
    // Top Documents Chart
    const topDocsCtx = document.getElementById('topDocumentsChart').getContext('2d');
    const topDocsChart = new Chart(topDocsCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($topDocumentLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($topDocumentViews); ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(168, 85, 247, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(239, 68, 68, 0.7)'
                ],
                borderColor: [
                    'rgba(59, 130, 246, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(168, 85, 247, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(239, 68, 68, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                title: {
                    display: true,
                    text: 'Most Viewed Documents'
                }
            }
        }
    });
    
    // Storage Chart
    const storageCtx = document.getElementById('storageChart').getContext('2d');
    const storageChart = new Chart(storageCtx, {
        type: 'pie',
        data: {
            labels: ['Used Space', 'Free Space'],
            datasets: [{
                data: [
                    <?php echo $storageUsage['total_size']; ?>,
                    <?php echo max(0, MAX_STORAGE_SIZE - $storageUsage['total_size']); ?>
                ],
                backgroundColor: [
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(209, 213, 219, 0.7)'
                ],
                borderColor: [
                    'rgba(245, 158, 11, 1)',
                    'rgba(209, 213, 219, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                title: {
                    display: true,
                    text: 'Storage Usage'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return context.label + ': ' + formatBytes(value) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // Function to format bytes
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
});
</script>

<?php include 'footer.php'; ?>
