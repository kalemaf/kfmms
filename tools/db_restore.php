<?php
/**
 * Database restore helper.
 *
 * Usage:
 *   php tools/db_restore.php --file=backup_filename.sql
 */

require_once __DIR__ . '/../config.inc.php';

if (PHP_SAPI !== 'cli') {
    echo "This helper is CLI-only.\n";
    exit(1);
}

$options = getopt('', ['file:', 'help']);
if (isset($options['help']) || empty($options['file'])) {
    echo "Usage: php tools/db_restore.php --file=backup_filename" . PHP_EOL;
    echo "  --file    Backup file path to restore. This action is destructive." . PHP_EOL;
    exit(0);
}

$backupFile = $options['file'];
if (!file_exists($backupFile)) {
    fwrite(STDERR, "Backup file not found: {$backupFile}\n");
    exit(1);
}

echo "WARNING: Restoring from {$backupFile} will overwrite current database contents." . PHP_EOL;
echo "Type YES to continue: ";
$confirmation = trim(fgets(STDIN));
if ($confirmation !== 'YES') {
    echo "Restore aborted.\n";
    exit(1);
}

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

    $backupBeforeRestore = $db_file . '.restore-backup-' . date('Ymd_His');
    if (!copy($db_file, $backupBeforeRestore)) {
        fwrite(STDERR, "Unable to create local backup before restore.\n");
        exit(1);
    }

    if (!copy($backupFile, $db_file)) {
        fwrite(STDERR, "Restore failed copying backup file to database file.\n");
        exit(1);
    }

    echo "SQLite restore completed. Previous database preserved at: {$backupBeforeRestore}\n";
    exit(0);
}

$mysql = find_executable('mysql');
if (!$mysql) {
    fwrite(STDERR, "mysql executable not found in PATH. Install MySQL client tools.\n");
    exit(1);
}

if ($password !== '') {
    putenv('MYSQL_PWD=' . $password);
}

$hostArg = escapeshellarg($hostName);
$userArg = escapeshellarg($userName);
$dbArg = escapeshellarg($databaseName);
$fileArg = escapeshellarg($backupFile);
$command = sprintf('%s -h %s -u %s %s < %s', $mysql, $hostArg, $userArg, $dbArg, $fileArg);

list($returnCode, $commandOutput) = execute_command($command);
if ($returnCode !== 0) {
    fwrite(STDERR, "Restore command failed:\n{$commandOutput}\n");
    exit(1);
}

echo "MySQL restore completed from: {$backupFile}\n";
