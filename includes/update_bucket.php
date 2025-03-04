<?php
require_once 'config.php';
require_once 'database.php';

$db = getDbConnection();
$stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
$stmt->execute(['pdflink', 's3.bucket']);

echo "Bucket adı güncellendi.\n";

// Yeni değeri kontrol et
$stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 's3.bucket'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Yeni bucket adı: " . $result['setting_value'] . "\n"; 