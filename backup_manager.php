<?php
/**
 * SQLite Database Backup & Recovery Manager
 * Automated backup, rotation, and disaster recovery for CMMS
 * 
 * Usage:
 *   php backup_manager.php backup              # Create backup
 *   php backup_manager.php restore <filename>  # Restore from backup
 *   php backup_manager.php cleanup             # Rotate old backups
 *   php backup_manager.php verify              # Verify backup integrity
 *   php backup_manager.php schedule            # Show cron commands
 */

// Configuration
define('DB_FILE', __DIR__ . '/database/maintenix.db');
define('BACKUP_DIR', __DIR__ . '/database/backups');
define('BACKUP_RETENTION_DAYS', 30);
define('MAX_BACKUPS', 10);
define('LOG_FILE', __DIR__ . '/logs/backup.log');

// Ensure backup directory exists
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}

// Ensure logs directory exists
if (!is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

/**
 * Log a message with timestamp
 */
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
}

/**
 * Create a backup of the SQLite database
 */
function create_backup() {
    if (!file_exists(DB_FILE)) {
        log_message('Database file not found: ' . DB_FILE, 'ERROR');
        return false;
    }

    // Verify database integrity before backup
    if (!verify_database_integrity()) {
        log_message('Database integrity check failed. Backup cancelled.', 'ERROR');
        return false;
    }

    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = BACKUP_DIR . '/maintenix_' . $timestamp . '.db';
    
    try {
        // Copy database file
        if (!copy(DB_FILE, $backup_file)) {
            log_message('Failed to create backup file', 'ERROR');
            return false;
        }

        // Verify backup was created successfully
        if (!file_exists($backup_file) || filesize($backup_file) === 0) {
            log_message('Backup file created but appears empty or invalid', 'ERROR');
            @unlink($backup_file);
            return false;
        }

        // Set proper permissions
        chmod($backup_file, 0644);

        $file_size = round(filesize($backup_file) / 1024 / 1024, 2);
        log_message("✓ Backup created: {$backup_file} ({$file_size} MB)", 'SUCCESS');
        
        // Clean up old backups
        cleanup_old_backups();
        
        return true;
    } catch (Exception $e) {
        log_message('Backup creation failed: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Verify database integrity
 */
function verify_database_integrity() {
    if (!file_exists(DB_FILE)) {
        return false;
    }

    try {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Run PRAGMA integrity_check
        $result = $pdo->query('PRAGMA integrity_check');
        $check = $result->fetch(PDO::FETCH_ASSOC);
        
        $pdo = null;
        
        if ($check && $check['integrity_check'] === 'ok') {
            log_message('✓ Database integrity check passed', 'INFO');
            return true;
        } else {
            log_message('✗ Database integrity check failed: ' . ($check['integrity_check'] ?? 'unknown'), 'ERROR');
            return false;
        }
    } catch (Exception $e) {
        log_message('Integrity check error: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Restore database from backup
 */
function restore_backup($backup_file) {
    if (!file_exists($backup_file)) {
        log_message("Backup file not found: {$backup_file}", 'ERROR');
        return false;
    }

    // Verify backup integrity before restore
    try {
        $pdo = new PDO('sqlite:' . $backup_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $result = $pdo->query('PRAGMA integrity_check');
        $check = $result->fetch(PDO::FETCH_ASSOC);
        $pdo = null;
        
        if ($check['integrity_check'] !== 'ok') {
            log_message('Backup file integrity check failed', 'ERROR');
            return false;
        }
    } catch (Exception $e) {
        log_message('Cannot verify backup file: ' . $e->getMessage(), 'ERROR');
        return false;
    }

    try {
        // Create safety backup of current database
        $safety_backup = DB_FILE . '.before_restore_' . date('Y-m-d_H-i-s');
        if (file_exists(DB_FILE)) {
            copy(DB_FILE, $safety_backup);
            log_message("Safety backup created: {$safety_backup}", 'INFO');
        }

        // Restore from backup
        if (!copy($backup_file, DB_FILE)) {
            log_message('Failed to restore from backup', 'ERROR');
            return false;
        }

        chmod(DB_FILE, 0644);
        log_message("✓ Database restored from: {$backup_file}", 'SUCCESS');
        return true;
    } catch (Exception $e) {
        log_message('Restore failed: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Clean up old backups based on retention policy
 */
function cleanup_old_backups() {
    try {
        $files = array_diff(scandir(BACKUP_DIR), ['.', '..']);
        $backups = [];

        // Collect all backup files with their timestamps
        foreach ($files as $file) {
            if (preg_match('/maintenix_(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})\.db/', $file, $matches)) {
                $file_path = BACKUP_DIR . '/' . $file;
                $backups[$file_path] = filemtime($file_path);
            }
        }

        // Sort by timestamp (oldest first)
        asort($backups);

        // Remove excess backups (keep MAX_BACKUPS most recent)
        $backup_count = count($backups);
        if ($backup_count > MAX_BACKUPS) {
            $backups_to_remove = array_slice($backups, 0, $backup_count - MAX_BACKUPS);
            foreach ($backups_to_remove as $file_path => $timestamp) {
                if (unlink($file_path)) {
                    log_message("Removed old backup: " . basename($file_path), 'INFO');
                }
            }
        }

        // Remove backups older than retention days
        $cutoff_time = time() - (BACKUP_RETENTION_DAYS * 86400);
        foreach ($backups as $file_path => $timestamp) {
            if ($timestamp < $cutoff_time) {
                if (unlink($file_path)) {
                    log_message("Removed expired backup: " . basename($file_path), 'INFO');
                }
            }
        }
    } catch (Exception $e) {
        log_message('Cleanup error: ' . $e->getMessage(), 'ERROR');
    }
}

/**
 * List available backups
 */
function list_backups() {
    if (!is_dir(BACKUP_DIR)) {
        echo "Backup directory not found\n";
        return;
    }

    $files = array_diff(scandir(BACKUP_DIR), ['.', '..']);
    $backups = [];

    foreach ($files as $file) {
        if (preg_match('/maintenix_\d{4}-\d{2}-\d{2}/', $file)) {
            $file_path = BACKUP_DIR . '/' . $file;
            $backups[] = [
                'file' => $file,
                'path' => $file_path,
                'size' => filesize($file_path),
                'modified' => date('Y-m-d H:i:s', filemtime($file_path))
            ];
        }
    }

    if (empty($backups)) {
        echo "No backups found\n";
        return;
    }

    // Sort by date (newest first)
    usort($backups, function ($a, $b) {
        return filemtime($b['path']) - filemtime($a['path']);
    });

    echo "\n=== Available Backups ===\n";
    foreach ($backups as $backup) {
        $size = round($backup['size'] / 1024 / 1024, 2);
        echo "{$backup['file']} ({$size} MB) - {$backup['modified']}\n";
    }
    echo "\n";
}

/**
 * Show cron command for scheduled backups
 */
function show_schedule_commands() {
    $script_path = __FILE__;
    echo "\n=== Recommended Cron Jobs ===\n\n";
    echo "# Daily backup at 2 AM\n";
    echo "0 2 * * * php {$script_path} backup\n\n";
    echo "# Weekly integrity check (Sundays at 3 AM)\n";
    echo "0 3 * * 0 php {$script_path} verify\n\n";
    echo "# Cleanup old backups (1st of month at 4 AM)\n";
    echo "0 4 1 * * php {$script_path} cleanup\n\n";
}

// Main execution
$command = isset($argv[1]) ? $argv[1] : 'help';

switch ($command) {
    case 'backup':
        echo "Creating database backup...\n";
        create_backup();
        list_backups();
        break;

    case 'restore':
        if (!isset($argv[2])) {
            echo "Error: Backup filename required\n";
            echo "Usage: php backup_manager.php restore <filename>\n";
            list_backups();
            exit(1);
        }
        $backup_file = BACKUP_DIR . '/' . $argv[2];
        echo "Restoring from backup: {$argv[2]}...\n";
        restore_backup($backup_file);
        break;

    case 'cleanup':
        echo "Cleaning up old backups...\n";
        cleanup_old_backups();
        list_backups();
        break;

    case 'verify':
        echo "Verifying database integrity...\n";
        if (verify_database_integrity()) {
            echo "✓ Database is healthy\n";
        } else {
            echo "✗ Database has integrity issues\n";
            exit(1);
        }
        break;

    case 'list':
        list_backups();
        break;

    case 'schedule':
        show_schedule_commands();
        break;

    case 'help':
    default:
        echo <<<EOT
SQLite Database Backup & Recovery Manager

USAGE:
  php backup_manager.php <command> [options]

COMMANDS:
  backup              Create a new database backup
  restore <filename>  Restore database from backup
  cleanup             Remove old/expired backups
  verify              Check database integrity
  list                List all available backups
  schedule            Show recommended cron jobs
  help                Show this help message

EXAMPLES:
  php backup_manager.php backup
  php backup_manager.php restore maintenix_2026-05-03_14-30-00.db
  php backup_manager.php verify

FEATURES:
  ✓ Automatic backup rotation (keeps {MAX_BACKUPS} latest)
  ✓ Time-based retention ({BACKUP_RETENTION_DAYS} days)
  ✓ Database integrity verification
  ✓ Safety backup before restore
  ✓ Comprehensive logging to: {LOG_FILE}

CONFIGURATION:
  Backup directory: {BACKUP_DIR}
  Database file:    {DB_FILE}
  Retention:        {BACKUP_RETENTION_DAYS} days
  Max backups:      {MAX_BACKUPS}

EOT;
        break;
}
?>
