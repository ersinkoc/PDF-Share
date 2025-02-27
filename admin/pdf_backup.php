<?php
/**
 * PDF Backup Generator
 * 
 * Creates a PDF document containing QR codes for all documents in the system.
 * This serves as an additional backup method that can be printed or stored digitally.
 */

// Include initialization file
require_once '../includes/init.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check if TCPDF is installed
if (!file_exists(BASE_PATH . 'includes/tcpdf/tcpdf.php')) {
    header('Location: install_tcpdf.php');
    exit;
}

// Initialize variables
$error = '';
$success = '';

// Generate CSRF token if not exists
if (!isset($_SESSION['pdf_backup_csrf_token'])) {
    $_SESSION['pdf_backup_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['pdf_backup_csrf_token'];

// Process PDF backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_pdf_backup'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['pdf_backup_csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        try {
            // Get database connection
            $db = getDbConnection();
            
            // Get all documents
            $stmt = $db->prepare("SELECT id, title, filename, uuid, short_url, qr_code FROM documents ORDER BY title");
            $stmt->execute();
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($documents) === 0) {
                $error = 'No documents found to backup.';
            } else {
                // Create PDF document
                require_once BASE_PATH . 'includes/tcpdf/tcpdf.php';
                
                // Create new PDF document
                $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                
                // Set document information
                $pdf->SetCreator('PDF QR Link');
                $pdf->SetAuthor('PDF QR Link System');
                $pdf->SetTitle('PDF QR Link Backup');
                $pdf->SetSubject('PDF QR Link Document Backup');
                $pdf->SetKeywords('PDF, QR, Backup');
                
                // Set default header data
                $pdf->SetHeaderData('', 0, 'PDF QR Link Backup', 'Generated on ' . date('Y-m-d H:i:s'));
                
                // Set header and footer fonts
                $pdf->setHeaderFont(Array('helvetica', '', 10));
                $pdf->setFooterFont(Array('helvetica', '', 8));
                
                // Set default monospaced font
                $pdf->SetDefaultMonospacedFont('courier');
                
                // Set margins
                $pdf->SetMargins(15, 20, 15);
                $pdf->SetHeaderMargin(5);
                $pdf->SetFooterMargin(10);
                
                // Set auto page breaks
                $pdf->SetAutoPageBreak(TRUE, 15);
                
                // Set image scale factor
                $pdf->setImageScale(1.25);
                
                // Add first page
                $pdf->AddPage();
                
                // Set font
                $pdf->SetFont('helvetica', 'B', 16);
                
                // Title
                $pdf->Cell(0, 10, 'PDF QR Link System Backup', 0, 1, 'C');
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s'), 0, 1, 'C');
                $pdf->Ln(5);
                
                // Introduction text
                $pdf->SetFont('helvetica', '', 11);
                $pdf->MultiCell(0, 10, 'This document contains QR codes for all documents in the system. Each QR code links to the corresponding document. This backup can be used to restore access to your documents in case of system failure.', 0, 'L', 0);
                $pdf->Ln(5);
                
                // Information about recovery
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Cell(0, 10, 'Recovery Instructions:', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 10);
                $pdf->MultiCell(0, 10, "1. Scan any QR code to access the document\n2. If the system is offline, the QR code contains the document's UUID which can be used for recovery\n3. Each document entry contains the original filename for manual recovery", 0, 'L', 0);
                $pdf->Ln(10);
                
                // Document entries
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->Cell(0, 10, 'Document Inventory', 0, 1, 'L');
                $pdf->Ln(5);
                
                // Set column widths
                $col1Width = 80; // Title
                $col2Width = 80; // QR Code
                
                // Loop through documents
                foreach ($documents as $index => $document) {
                    // Add a new page if needed
                    if ($index > 0 && $index % 3 === 0) {
                        $pdf->AddPage();
                    }
                    
                    // Document container
                    $pdf->SetFillColor(245, 245, 245);
                    $pdf->Rect(15, $pdf->GetY(), 180, 80, 'F');
                    
                    // Document title and info
                    $pdf->SetFont('helvetica', 'B', 12);
                    $pdf->Cell(0, 10, ($index + 1) . '. ' . $document['title'], 0, 1, 'L');
                    
                    $startY = $pdf->GetY();
                    
                    // Document details
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetX(20);
                    $pdf->MultiCell($col1Width, 5, 
                        "Filename: " . $document['filename'] . "\n" .
                        "UUID: " . $document['uuid'] . "\n" .
                        "Short URL: " . $document['short_url'] . "\n" .
                        "URL: " . BASE_URL . "view.php?uuid=" . $document['uuid'],
                        0, 'L', 0);
                    
                    // QR Code
                    $qrPath = BASE_PATH . $document['qr_code'];
                    if (file_exists($qrPath)) {
                        $pdf->SetXY(110, $startY);
                        $pdf->Image($qrPath, '', '', 60, 60, 'PNG');
                    } else {
                        // Generate QR code if not exists
                        $url = BASE_URL . "view.php?uuid=" . $document['uuid'];
                        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($url);
                        $pdf->SetXY(110, $startY);
                        $pdf->Image($qrUrl, '', '', 60, 60, 'PNG');
                    }
                    
                    $pdf->Ln(70);
                }
                
                // Add metadata page
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 16);
                $pdf->Cell(0, 10, 'System Metadata', 0, 1, 'C');
                $pdf->Ln(5);
                
                // System information
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Cell(0, 10, 'System Information:', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 10);
                
                // Get system information
                $totalDocuments = count($documents);
                $storageInfo = getTotalStorageUsage();
                
                $pdf->MultiCell(0, 5, 
                    "Total Documents: " . $totalDocuments . "\n" .
                    "Total PDF Storage: " . (isset($storageInfo['pdf_size']) ? formatBytes($storageInfo['pdf_size']) : formatBytes($storageInfo['total_size'] * 0.8)) . "\n" .
                    "Total QR Code Storage: " . (isset($storageInfo['qr_size']) ? formatBytes($storageInfo['qr_size']) : formatBytes($storageInfo['total_size'] * 0.2)) . "\n" .
                    "Backup Date: " . date('Y-m-d H:i:s') . "\n" .
                    "System Version: " . (defined('APP_VERSION') ? APP_VERSION : '1.0') . "\n",
                    0, 'L', 0);
                
                // Output PDF
                $pdfFileName = 'pdf_qr_backup_' . date('Y-m-d_H-i-s') . '.pdf';
                $pdf->Output($pdfFileName, 'D');
                
                // Log the backup activity
                logActivity('backup', 'system', 0, [
                    'type' => 'pdf_qr_backup',
                    'document_count' => count($documents)
                ]);
                
                exit;
            }
        } catch (Exception $e) {
            $error = 'Error creating PDF backup: ' . $e->getMessage();
        }
    }
}

// Include header
$pageTitle = 'PDF QR Backup';
include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">PDF QR Backup</h1>
    
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
        <h2 class="text-xl font-bold mb-4">Create PDF QR Backup</h2>
        
        <div class="mb-6">
            <p class="text-gray-600 mb-4">
                Generate a PDF document containing QR codes for all documents in the system. This backup can be printed or stored digitally as an additional recovery method.
            </p>
            
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4" role="alert">
                <p class="font-bold">Benefits of PDF QR Backup</p>
                <ul class="list-disc ml-5 mt-2">
                    <li>Physical backup that can be printed and stored offline</li>
                    <li>Contains QR codes that link directly to documents</li>
                    <li>Includes document UUIDs for recovery purposes</li>
                    <li>Can be used even if the main system is unavailable</li>
                </ul>
            </div>
        </div>
        
        <form action="pdf_backup.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="flex items-center justify-between">
                <button type="submit" name="create_pdf_backup" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="bi bi-file-earmark-pdf mr-2"></i> Generate PDF QR Backup
                </button>
                
                <a href="import_export.php" class="text-indigo-500 hover:text-indigo-700">
                    <i class="bi bi-arrow-left mr-1"></i> Back to Import & Export
                </a>
            </div>
        </form>
    </div>
    
    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <h2 class="text-xl font-bold mb-4">How to Use PDF QR Backup</h2>
        
        <div class="mb-4">
            <h3 class="font-bold text-lg mb-2">For Disaster Recovery</h3>
            <p class="text-gray-600 mb-2">
                In case of complete system failure, the PDF backup provides:
            </p>
            <ul class="list-disc ml-5 text-gray-600">
                <li>Document inventory with original filenames</li>
                <li>UUIDs for all documents</li>
                <li>QR codes that can be scanned to access documents</li>
                <li>System metadata for verification purposes</li>
            </ul>
        </div>
        
        <div class="mb-4">
            <h3 class="font-bold text-lg mb-2">For Document Access</h3>
            <p class="text-gray-600 mb-2">
                The PDF backup allows users to:
            </p>
            <ul class="list-disc ml-5 text-gray-600">
                <li>Quickly scan QR codes to access documents</li>
                <li>Share documents by sharing the PDF page</li>
                <li>Have an offline reference to all documents in the system</li>
            </ul>
        </div>
        
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mt-4" role="alert">
            <p class="font-bold">Important Note</p>
            <p>While the PDF QR backup provides access information, it does not contain the actual PDF document contents. For full content backup, use the Full System Backup option.</p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
