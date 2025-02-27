<?php
/**
 * TCPDF Library Installer
 * 
 * Downloads and installs the TCPDF library for PDF generation
 */

// Include initialization file
require_once '../includes/init.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Initialize variables
$error = '';
$success = '';

// Process installation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_tcpdf'])) {
    try {
        // Create tcpdf directory if it doesn't exist
        $tcpdfDir = BASE_PATH . 'includes/tcpdf';
        if (!file_exists($tcpdfDir)) {
            mkdir($tcpdfDir, 0777, true);
        }
        
        // Download TCPDF library
        $tcpdfUrl = 'https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.2.zip';
        $zipFile = sys_get_temp_dir() . '/tcpdf.zip';
        
        // Use cURL to download the file
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tcpdfUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception('Error downloading TCPDF: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        // Save ZIP file
        file_put_contents($zipFile, $data);
        
        // Extract ZIP file
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new Exception("Cannot open ZIP archive");
        }
        
        // Create temporary directory for extraction
        $tempDir = sys_get_temp_dir() . '/tcpdf_extract_' . time();
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        $zip->extractTo($tempDir);
        $zip->close();
        
        // Copy necessary files to tcpdf directory
        $sourceDir = $tempDir . '/TCPDF-6.6.2';
        
        // Copy main TCPDF files
        copy($sourceDir . '/tcpdf.php', $tcpdfDir . '/tcpdf.php');
        copy($sourceDir . '/tcpdf_autoconfig.php', $tcpdfDir . '/tcpdf_autoconfig.php');
        copy($sourceDir . '/tcpdf_barcodes_1d.php', $tcpdfDir . '/tcpdf_barcodes_1d.php');
        copy($sourceDir . '/tcpdf_barcodes_2d.php', $tcpdfDir . '/tcpdf_barcodes_2d.php');
        copy($sourceDir . '/tcpdf_import.php', $tcpdfDir . '/tcpdf_import.php');
        copy($sourceDir . '/tcpdf_parser.php', $tcpdfDir . '/tcpdf_parser.php');
        
        // Create necessary directories
        $dirs = ['config', 'fonts', 'include'];
        foreach ($dirs as $dir) {
            if (!file_exists($tcpdfDir . '/' . $dir)) {
                mkdir($tcpdfDir . '/' . $dir, 0777, true);
            }
        }
        
        // Copy config files
        copy($sourceDir . '/config/tcpdf_config.php', $tcpdfDir . '/config/tcpdf_config.php');
        
        // Copy font files (basic fonts only)
        $fontFiles = [
            'courier.php', 'courierb.php', 'courierbi.php', 'courieri.php',
            'helvetica.php', 'helveticab.php', 'helveticabi.php', 'helveticai.php',
            'times.php', 'timesb.php', 'timesbi.php', 'timesi.php',
            'zapfdingbats.php'
        ];
        
        foreach ($fontFiles as $fontFile) {
            copy($sourceDir . '/fonts/' . $fontFile, $tcpdfDir . '/fonts/' . $fontFile);
        }
        
        // Copy include files
        $includeFiles = [
            'tcpdf_colors.php', 'tcpdf_filters.php', 'tcpdf_font_data.php',
            'tcpdf_fonts.php', 'tcpdf_images.php', 'tcpdf_static.php'
        ];
        
        foreach ($includeFiles as $includeFile) {
            copy($sourceDir . '/include/' . $includeFile, $tcpdfDir . '/include/' . $includeFile);
        }
        
        // Clean up
        unlink($zipFile);
        rrmdir($tempDir);
        
        $success = 'TCPDF library installed successfully.';
        
        // Log the activity
        logActivity('install', 'library', 0, [
            'library' => 'TCPDF',
            'version' => '6.6.2'
        ]);
    } catch (Exception $e) {
        $error = 'Error installing TCPDF: ' . $e->getMessage();
    }
}

// Check if TCPDF is already installed
$tcpdfInstalled = file_exists(BASE_PATH . 'includes/tcpdf/tcpdf.php');

// Include header
$pageTitle = 'Install TCPDF';
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Install TCPDF Library</h1>
    
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
        </div>
    <?php endif; ?>
    
    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <h2 class="text-xl font-bold mb-4">TCPDF Library Installation</h2>
        
        <?php if ($tcpdfInstalled): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">TCPDF library is already installed.</span>
            </div>
            
            <p class="mb-4 text-gray-600">
                The TCPDF library is already installed and ready to use. You can now generate PDF QR backups.
            </p>
            
            <div class="flex items-center justify-between">
                <a href="pdf_import_export.php" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="bi bi-file-earmark-pdf mr-2"></i> Go to PDF QR Backup
                </a>
                
                <a href="import_export.php" class="text-indigo-500 hover:text-indigo-700">
                    <i class="bi bi-arrow-left mr-1"></i> Back to Import & Export
                </a>
            </div>
        <?php else: ?>
            <p class="mb-4 text-gray-600">
                The TCPDF library is required to generate PDF QR backups. Click the button below to download and install the library.
            </p>
            
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
                <p class="font-bold">Note</p>
                <p>This will download and install the TCPDF library (version 6.6.2) from GitHub. The installation may take a few moments.</p>
            </div>
            
            <form action="install_tcpdf.php" method="post">
                <button type="submit" name="install_tcpdf" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="bi bi-download mr-2"></i> Install TCPDF Library
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
