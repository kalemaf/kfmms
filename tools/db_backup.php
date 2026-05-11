<?php
/**
 * Database backup helper.
 *
 * Usage:
 *   php tools/db_backup.php [--file=backup_filename.sql]
 */

require_once __DIR__ . '/../config.inc.php';

if (PHP_SAPI !== 'cli') {
    echo "This helper is CLI-only.\n";
    exit(1);
}

if (!$db_available) {
    fwrite(STDERR, "Database unavailable: {$db_error}\n");
    exit(1);
}

$options = getopt('', ['file::', 'help']);
if (isset($options['help'])) {
    echo "Usage: php tools/db_backup.php [--file=backup_filename]" . PHP_EOL;
    echo "  --file    Optional backup file name. Defaults to backups/db_backup_YYYYmmdd_HHMMSS.sql" . PHP_EOL;
    exit(0);
}

$backupDir = env('BACKUP_DIR', __DIR__ . '/../backups');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$defaultName = sprintf('db_backup_%s.%s', date('Ymd_His'), $db_type === 'sqlite' ? 'db' : 'sql');
$filename = isset($options['file']) ? basename($options['file']) : $defaultName;
$backupFile = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

function find_executable($name) {
    $cmd = stripos(PHP_OS, 'WIN') === 0 ? "where $name" : "command -v $name";
    exec($cmd, $output, $returnCode);
    if ($returnCode === 0 && !empty($output)) {
        return trim($output[0]);
    }
    return false;
}

function execute_command($command) {
    exec($command . ' 2>&1', $output, $returnCode);
    return [$returnCode, implode(PHP_EOL, $output)];
}

if ($db_type === 'sqlite') {
    if (!file_exists($db_file)) {
        fwrite(STDERR, "SQLite database file not found: {$db_file}\n");
        exit(1);
    }
    if (!copy($db_file, $backupFile)) {
        fwrite(STDERR, "Failed to copy SQLite database to backup location.\n");
        exit(1);
    }
    echo "SQLite backup created: {$backupFile}\n";
    exit(0);
}

$mysqldump = find_executable('mysqldump');
if (!$mysqldump) {
    fwrite(STDERR, "mysqldump executable not found in PATH. Install MySQL client tools.\n");
    exit(1);
}

if ($password !== '') {
    putenv('MYSQL_PWD=' . $password);
}

$hostArg = escapeshellarg($hostName);
$userArg = escapeshellarg($userName);
$dbArg = escapeshellarg($databaseName);
$backupArg = escapeshellarg($backupFile);
$command = sprintf('%s --single-transaction --quick --skip-lock-tables -h %s -u %s %s > %s', $mysqldump, $hostArg, $userArg, $dbArg, $backupArg);

list($returnCode, $commandOutput) = execute_command($command);
if ($returnCode !== 0) {
    fwrite(STDERR, "Backup command failed:\n{$commandOutput}\n");
    exit(1);
}

echo "MySQL backup created: {$backupFile}\n";
