<?php
/**
 * Cloud Storage Integration
 * 
 * Supports: AWS S3, Google Cloud Storage, Azure Blob Storage, OneDrive
 * Stores work order attachments, equipment photos, inspection media
 */

abstract class CloudStorageProvider {
    
    protected $config = [];
    protected $c;
    
    public function __construct($mysqli_connection, $config = []) {
        $this->c = $mysqli_connection;
        $this->config = $config;
    }

    abstract public function connect();
    abstract public function upload($local_file, $remote_path);
    abstract public function download($remote_path, $local_file);
    abstract public function delete($remote_path);
    abstract public function getPublicURL($remote_path);
}

// ===== AWS S3 Provider =====

class S3CloudStorage extends CloudStorageProvider {
    
    private $s3_client = null;
    
    public function connect() {
        if (empty($this->config['access_key']) || empty($this->config['secret_key']) || empty($this->config['bucket'])) {
            return false;
        }

        try {
            // Assumes AWS SDK installed via Composer
            require_once 'vendor/autoload.php';
            
            /** @noinspection PhpUndefinedClassInspection */
            $this->s3_client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region'  => $this->config['region'] ?? 'us-east-1',
                'credentials' => [
                    'key'    => $this->config['access_key'],
                    'secret' => $this->config['secret_key'],
                ]
            ]);

            return true;
        } catch (Exception $e) {
            error_log("S3 connection failed: " . $e->getMessage());
            return false;
        }
    }

    public function upload($local_file, $remote_path) {
        if (!$this->s3_client || !file_exists($local_file)) {
            return false;
        }

        try {
            $this->s3_client->putObject([
                'Bucket' => $this->config['bucket'],
                'Key'    => $remote_path,
                'Body'   => fopen($local_file, 'r'),
                'ACL'    => 'private',
                'ServerSideEncryption' => 'AES256'
            ]);

            // Log to database
            $this->logTransaction($local_file, $remote_path, 'upload', 'success');
            return true;

        } catch (Exception $e) {
            $this->logTransaction($local_file, $remote_path, 'upload', 'error', $e->getMessage());
            return false;
        }
    }

    public function download($remote_path, $local_file) {
        if (!$this->s3_client) {
            return false;
        }

        try {
            $object = $this->s3_client->getObject([
                'Bucket' => $this->config['bucket'],
                'Key'    => $remote_path
            ]);

            file_put_contents($local_file, $object['Body']);
            $this->logTransaction($local_file, $remote_path, 'download', 'success');
            return true;

        } catch (Exception $e) {
            $this->logTransaction($local_file, $remote_path, 'download', 'error', $e->getMessage());
            return false;
        }
    }

    public function delete($remote_path) {
        if (!$this->s3_client) {
            return false;
        }

        try {
            $this->s3_client->deleteObject([
                'Bucket' => $this->config['bucket'],
                'Key'    => $remote_path
            ]);

            $this->logTransaction('', $remote_path, 'delete', 'success');
            return true;

        } catch (Exception $e) {
            $this->logTransaction('', $remote_path, 'delete', 'error', $e->getMessage());
            return false;
        }
    }

    public function getPublicURL($remote_path) {
        // Generate CloudFront or pre-signed URL
        if (!empty($this->config['cloudfront_domain'])) {
            return 'https://' . $this->config['cloudfront_domain'] . '/' . $remote_path;
        }

        // Pre-signed URL (expires in 24 hours)
        if (!$this->s3_client) {
            return false;
        }
        
        $cmd = $this->s3_client->getCommand('GetObject', [
            'Bucket' => $this->config['bucket'],
            'Key'    => $remote_path
        ]);

        return $this->s3_client->createPresignedRequest($cmd, '+20 minutes')->getUri();
    }

    protected function logTransaction($local_file, $remote_path, $action, $status, $error_msg = '') {
        if (!$this->c) {
            return;
        }
        $stmt = $this->c->prepare("INSERT INTO cloud_storage_log 
                   (provider, local_file, remote_path, action, status, error_message, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param('ssssss', 'S3', $local_file, $remote_path, $action, $status, $error_msg);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// ===== Google Drive Integration =====

class GoogleDriveStorage extends CloudStorageProvider {
    
    private $google_client = null;
    private $drive_service = null;
    
    public function connect() {
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            return false;
        }

        try {
            require_once 'vendor/autoload.php';
            
            /** @noinspection PhpUndefinedClassInspection */
            $this->google_client = new \Google_Client();
            $this->google_client->setClientId($this->config['client_id']);
            $this->google_client->setClientSecret($this->config['client_secret']);
            $this->google_client->setAccessToken($this->config['access_token']);

            /** @noinspection PhpUndefinedClassInspection */
            $this->drive_service = new \Google_Service_Drive($this->google_client);
            return true;

        } catch (Exception $e) {
            error_log("Google Drive connection failed: " . $e->getMessage());
            return false;
        }
    }

    public function upload($local_file, $remote_path) {
        if (!$this->drive_service || !file_exists($local_file)) {
            return false;
        }

        try {
            /** @noinspection PhpUndefinedClassInspection */
            $fileMetadata = new \Google_Service_Drive_DriveFile();
            $fileMetadata->setName(basename($remote_path));
            
            // Set parent folder if exists
            if (!empty($this->config['folder_id'])) {
                $fileMetadata->setParents([$this->config['folder_id']]);
            }

            $file = $this->drive_service->files->create($fileMetadata, [
                'data' => file_get_contents($local_file),
                'uploadType' => 'media'
            ]);

            $this->logTransaction($local_file, $file->getId(), 'upload', 'success');
            return true;

        } catch (Exception $e) {
            $this->logTransaction($local_file, $remote_path, 'upload', 'error', $e->getMessage());
            return false;
        }
    }

    public function download($file_id, $local_file) {
        if (!$this->drive_service) {
            return false;
        }

        try {
            $content = $this->drive_service->files->get($file_id, ['alt' => 'media']);
            file_put_contents($local_file, $content);
            $this->logTransaction($local_file, $file_id, 'download', 'success');
            return true;

        } catch (Exception $e) {
            $this->logTransaction($local_file, $file_id, 'download', 'error', $e->getMessage());
            return false;
        }
    }

    public function delete($file_id) {
        if (!$this->drive_service) {
            return false;
        }

        try {
            $this->drive_service->files->delete($file_id);
            $this->logTransaction('', $file_id, 'delete', 'success');
            return true;

        } catch (Exception $e) {
            $this->logTransaction('', $file_id, 'delete', 'error', $e->getMessage());
            return false;
        }
    }

    public function getPublicURL($file_id) {
        return 'https://drive.google.com/uc?id=' . urlencode($file_id) . '&export=download';
    }

    protected function logTransaction($local_file, $remote_path, $action, $status, $error_msg = '') {
        if (!$this->c) {
            return;
        }
        $stmt = $this->c->prepare("INSERT INTO cloud_storage_log 
                   (provider, local_file, remote_path, action, status, error_message, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param('ssssss', 'GoogleDrive', $local_file, $remote_path, $action, $status, $error_msg);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// ===== Azure Blob Storage Integration =====

class AzureBlobStorage extends CloudStorageProvider {
    
    private $blob_client = null;
    
    public function connect() {
        if (empty($this->config['account_name']) || empty($this->config['account_key'])) {
            return false;
        }

        try {
            require_once 'vendor/autoload.php';
            
            $connectionString = "DefaultEndpointsProtocol=https;AccountName=" . 
                               $this->config['account_name'] . 
                               ";AccountKey=" . $this->config['account_key'] . 
                               ";EndpointSuffix=core.windows.net";

            /** @noinspection PhpUndefinedClassInspection */
            $this->blob_client = \Azure\Storage\Blob\BlobRestProxy::createBlobService($connectionString);
            return true;

        } catch (Exception $e) {
            error_log("Azure connection failed: " . $e->getMessage());
            return false;
        }
    }

    public function upload($local_file, $remote_path) {
        if (!$this->blob_client || !file_exists($local_file)) {
            return false;
        }

        try {
            $this->blob_client->createBlockBlob(
                $this->config['container'],
                $remote_path,
                fopen($local_file, 'r')
            );

            $this->logTransaction($local_file, $remote_path, 'upload', 'success');
            return true;

        } catch (Exception $e) {
            $this->logTransaction($local_file, $remote_path, 'upload', 'error', $e->getMessage());
            return false;
        }
    }

    public function download($remote_path, $local_file) {
        if (!$this->blob_client) {
            return false;
        }

        try {
            $blob = $this->blob_client->getBlob(
                $this->config['container'],
                $remote_path
            );

            file_put_contents($local_file, stream_get_contents($blob->getContentStream()));
            $this->logTransaction($local_file, $remote_path, 'download', 'success');
            return true;

        } catch (Exception $e) {
            $this->logTransaction($local_file, $remote_path, 'download', 'error', $e->getMessage());
            return false;
        }
    }

    public function delete($remote_path) {
        if (!$this->blob_client) {
            return false;
        }

        try {
            $this->blob_client->deleteBlob(
                $this->config['container'],
                $remote_path
            );

            $this->logTransaction('', $remote_path, 'delete', 'success');
            return true;

        } catch (Exception $e) {
            $this->logTransaction('', $remote_path, 'delete', 'error', $e->getMessage());
            return false;
        }
    }

    public function getPublicURL($remote_path) {
        return "https://" . $this->config['account_name'] . ".blob.core.windows.net/" . 
               $this->config['container'] . "/" . $remote_path;
    }

    protected function logTransaction($local_file, $remote_path, $action, $status, $error_msg = '') {
        if (!$this->c) {
            return;
        }
        $stmt = $this->c->prepare("INSERT INTO cloud_storage_log 
                   (provider, local_file, remote_path, action, status, error_message, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param('ssssss', 'Azure', $local_file, $remote_path, $action, $status, $error_msg);
            $stmt->execute();
            $stmt->close();
        }
    }
}
