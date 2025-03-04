<?php

/**
 * S3Storage Class
 * 
 * Handles S3 storage operations
 */
class S3Storage {
    private $s3;
    private $bucket;
    private $settings;
    private $isInitialized = false;
    
    public function __construct($config = null) {
        if ($config) {
            $this->settings = array_merge([
                'provider' => 's3',
                'endpoint' => '',
                'region' => 'us-east-1',
                'bucket' => '',
                'access_key' => '',
                'secret_key' => '',
                'use_path_style' => true,
                'ssl_verify' => true,
                'auto_backup' => true,
                'cleanup_days' => 30,
                'cleanup_enabled' => false
            ], $config);
            
            $this->bucket = $this->settings['bucket'];
            $this->initializeS3Client();
        } else {
            $this->loadSettings();
            if ($this->isConfigured()) {
                try {
                    $this->initializeS3Client();
                } catch (Exception $e) {
                    error_log('S3 initialization error: ' . $e->getMessage());
                }
            }
        }
    }
    
    private function loadSettings() {
        $this->settings = [
            'provider' => getSettingValue('s3.provider', 's3'),
            'endpoint' => getSettingValue('s3.endpoint', ''),
            'region' => getSettingValue('s3.region', 'us-east-1'), // Varsayılan bölge
            'bucket' => getSettingValue('s3.bucket', ''),
            'access_key' => getSettingValue('s3.access_key', ''),
            'secret_key' => getSettingValue('s3.secret_key', ''),
            'use_path_style' => getSettingValue('s3.use_path_style', '0') === '1',
            'ssl_verify' => getSettingValue('s3.ssl_verify', '1') === '1',
            'auto_backup' => getSettingValue('storage.document.auto_backup', '1') === '1',
            'cleanup_days' => (int)getSettingValue('storage.document.cleanup_days', '30'),
            'cleanup_enabled' => getSettingValue('storage.document.cleanup_enabled', '0') === '1'
        ];
        
        $this->bucket = $this->settings['bucket'];
    }
    
    private function initializeS3Client() {
        require_once BASE_PATH . 'vendor/autoload.php';
        
        $options = [
            'version' => 'latest',
            'region' => $this->settings['region'],
            'credentials' => [
                'key' => $this->settings['access_key'],
                'secret' => $this->settings['secret_key']
            ],
            'use_path_style_endpoint' => $this->settings['use_path_style'],
            'debug' => false
        ];
        
        // MinIO endpoint ayarı
        if (!empty($this->settings['endpoint'])) {
            $options['endpoint'] = $this->settings['endpoint'];
        }
        
        // SSL doğrulama ayarı
        if (!$this->settings['ssl_verify']) {
            $options['http'] = [
                'verify' => false
            ];
        }
        
        try {
            $this->s3 = new \Aws\S3\S3Client($options);
            $this->isInitialized = true;
        } catch (Exception $e) {
            error_log('S3 init error: ' . $e->getMessage());
            throw new Exception('S3 client initialization failed: ' . $e->getMessage());
        }
    }
    
    private function ensureBucketExists() {
        try {
            // Bucket var mı kontrol et
            if (!$this->s3->doesBucketExist($this->bucket)) {
                // Bucket yoksa oluştur
                $this->s3->createBucket([
                    'Bucket' => $this->bucket
                ]);
                
                // Bucket oluşturulana kadar bekle
                $this->s3->waitUntil('BucketExists', [
                    'Bucket' => $this->bucket
                ]);
                
                error_log('Created new bucket: ' . $this->bucket);
            }
        } catch (Exception $e) {
            throw new Exception('Failed to ensure bucket exists: ' . $e->getMessage());
        }
    }
    
    public function isConfigured() {
        return $this->settings['provider'] !== 'local' && 
               !empty($this->settings['access_key']) && 
               !empty($this->settings['secret_key']) && 
               !empty($this->settings['bucket']);
    }
    
    private function ensureInitialized() {
        if (!$this->isInitialized) {
            throw new Exception('S3 client is not initialized. Please check your configuration.');
        }
    }
    
    public function uploadFile($localPath, $s3Key) {
        $this->ensureInitialized();
        try {
            $result = $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                'SourceFile' => $localPath,
                'ACL' => 'private'
            ]);
            return $result['ObjectURL'];
        } catch (Exception $e) {
            error_log('Upload error: ' . $e->getMessage());
            throw new Exception('Failed to upload file to S3: ' . $e->getMessage());
        }
    }
    
    public function downloadFile($s3Key, $localPath) {
        $this->ensureInitialized();
        try {
            $this->s3->getObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
                'SaveAs' => $localPath
            ]);
            return true;
        } catch (Exception $e) {
            error_log('Download error: ' . $e->getMessage());
            throw new Exception('Failed to download file from S3: ' . $e->getMessage());
        }
    }
    
    public function deleteFile($s3Key) {
        $this->ensureInitialized();
        try {
            $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key
            ]);
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to delete file from S3: ' . $e->getMessage());
        }
    }
    
    public function listFiles($prefix = '') {
        $this->ensureInitialized();
        try {
            $result = $this->s3->listObjects([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix
            ]);
            
            $files = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $files[] = [
                        'key' => $object['Key'],
                        'size' => $object['Size'],
                        'modified' => $object['LastModified']
                    ];
                }
            }
            return $files;
        } catch (Exception $e) {
            throw new Exception('Failed to list files from S3: ' . $e->getMessage());
        }
    }
    
    public function backupToS3($localPath, $backupName) {
        $this->ensureInitialized();
        try {
            $s3Key = 'backups/' . $backupName;
            return $this->uploadFile($localPath, $s3Key);
        } catch (Exception $e) {
            throw new Exception('Failed to backup to S3: ' . $e->getMessage());
        }
    }
    
    public function restoreFromS3($backupName, $localPath) {
        $this->ensureInitialized();
        try {
            $s3Key = 'backups/' . $backupName;
            return $this->downloadFile($s3Key, $localPath);
        } catch (Exception $e) {
            throw new Exception('Failed to restore from S3: ' . $e->getMessage());
        }
    }
    
    public function listBackups() {
        $this->ensureInitialized();
        try {
            return $this->listFiles('backups/');
        } catch (Exception $e) {
            throw new Exception('Failed to list backups from S3: ' . $e->getMessage());
        }
    }
    
    /**
     * Backup a document to S3
     * 
     * @param array $document Document data
     * @return bool True on success, false on failure
     */
    public function backupDocument($document) {
        if (!$this->isConfigured() || !isset($document['uuid'])) {
            return false;
        }
        
        try {
            $localPath = BASE_PATH . 'uploads/' . $document['filename'];
            if (!file_exists($localPath)) {
                throw new Exception('Local file not found: ' . $localPath);
            }
            
            $s3Key = 'documents/' . $document['uuid'] . '/' . basename($document['filename']);
            $s3Url = $this->uploadFile($localPath, $s3Key);
            
            // Update document record
            $db = getDbConnection();
            $stmt = $db->prepare("UPDATE documents SET 
                s3_url = ?, 
                s3_backup_date = CURRENT_TIMESTAMP 
                WHERE uuid = ?");
            $stmt->execute([$s3Url, $document['uuid']]);
            
            // Log activity
            logActivity('backup', 'document', $document['uuid'], [
                'action' => 'Document backed up to S3',
                's3_url' => $s3Url
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log('Failed to backup document: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Restore a document from S3
     * 
     * @param array $document Document data
     * @return bool True on success, false on failure
     */
    public function restoreDocument($document) {
        if (!$this->isConfigured() || !isset($document['uuid']) || empty($document['s3_url'])) {
            return false;
        }
        
        try {
            $s3Key = 'documents/' . $document['uuid'] . '/' . basename($document['filename']);
            $localPath = BASE_PATH . 'uploads/' . $document['filename'];
            
            // Create directory if not exists
            $dir = dirname($localPath);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Download from S3
            $this->downloadFile($s3Key, $localPath);
            
            // Update document record
            $db = getDbConnection();
            $stmt = $db->prepare("UPDATE documents SET 
                is_local = 1,
                last_accessed_date = CURRENT_TIMESTAMP 
                WHERE uuid = ?");
            $stmt->execute([$document['uuid']]);
            
            // Log activity
            logActivity('restore', 'document', $document['uuid'], [
                'action' => 'Document restored from S3'
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log('Failed to restore document: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old local files
     * 
     * @return array Statistics about cleanup
     */
    public function cleanupLocalFiles() {
        if (!$this->isConfigured() || !$this->settings['cleanup_enabled']) {
            return ['cleaned' => 0, 'errors' => 0];
        }
        
        $stats = ['cleaned' => 0, 'errors' => 0];
        
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT * FROM documents 
                WHERE is_local = 1 
                AND s3_url IS NOT NULL 
                AND last_accessed_date < datetime('now', '-' || ? || ' days')");
            $stmt->execute([$this->settings['cleanup_days']]);
            
            while ($doc = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $localPath = BASE_PATH . 'uploads/' . $doc['filename'];
                
                if (file_exists($localPath)) {
                    if (unlink($localPath)) {
                        // Update document record
                        $updateStmt = $db->prepare("UPDATE documents SET 
                            is_local = 0 
                            WHERE uuid = ?");
                        $updateStmt->execute([$doc['uuid']]);
                        
                        // Log activity
                        logActivity('cleanup', 'document', $doc['uuid'], [
                            'action' => 'Local file removed'
                        ]);
                        
                        $stats['cleaned']++;
                    } else {
                        $stats['errors']++;
                        error_log('Failed to delete local file: ' . $localPath);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error during local file cleanup: ' . $e->getMessage());
            $stats['errors']++;
        }
        
        return $stats;
    }
    
    /**
     * Check if automatic backup is enabled
     * 
     * @return bool
     */
    public function isAutoBackupEnabled() {
        return $this->isConfigured() && $this->settings['auto_backup'];
    }
    
    /**
     * Get document S3 status
     * 
     * @param array $document Document data
     * @return array Status information
     */
    public function getDocumentStatus($document) {
        return [
            'has_local' => $document['is_local'] == 1,
            'has_s3' => !empty($document['s3_url']),
            'last_backup' => $document['s3_backup_date'],
            'last_accessed' => $document['last_accessed_date'],
            'can_cleanup' => $this->settings['cleanup_enabled'] && 
                           $document['is_local'] == 1 && 
                           !empty($document['s3_url']) && 
                           !empty($document['last_accessed_date']) && 
                           strtotime($document['last_accessed_date']) < strtotime('-' . $this->settings['cleanup_days'] . ' days')
        ];
    }
} 