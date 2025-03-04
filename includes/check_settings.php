<?php
require_once 'config.php';
require_once 'database.php';

$settings = [
    's3.provider',
    's3.endpoint',
    's3.bucket',
    's3.region',
    's3.access_key',
    's3.secret_key',
    's3.use_path_style',
    's3.ssl_verify'
];

$db = getDbConnection();
$stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN (" . str_repeat('?,', count($settings)-1) . "?)");
$stmt->execute($settings);

echo "Mevcut S3 Ayarları:\n";
echo "==================\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['setting_key'] . ": " . $row['setting_value'] . "\n";
} 