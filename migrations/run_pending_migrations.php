<?php
/**
 * Run pending database migrations and record applied migration versions.
 *
 * Usage:
 *   php migrations/run_pending_migrations.php
 *   php migrations/run_pending_migrations.php --dry-run
 */

require_once __DIR__ . '/../config.inc.php';

if (PHP_SAPI !== 'cli') {
    echo "This migration runner is CLI-only.\n";
    exit(1);
}

if (!$db_available) {
    fwrite(STDERR, "Database unavailable: {$db_error}\n");
    exit(1);
}

$options = getopt('', ['dry-run', 'help']);
$dryRun = isset($options['dry-run']);
$showHelp = isset($options['help']);

if ($showHelp) {
    echo "Usage: php migrations/run_pending_migrations.php [--dry-run]" . PHP_EOL;
    echo "  --dry-run     List pending migrations without applying them." . PHP_EOL;
    exit(0);
}

$migrationDir = __DIR__;
$scriptName = basename(__FILE__);
$pattern = '/^\d+.*\.(sql|php)$/i';

function log_cli($message) {
    echo $message . PHP_EOL;
}

function quote_value($value, $connection, $db_type) {
    $value = (string)$value;
    if ($db_type === 'sqlite') {
        return "'" . str_replace("'", "''", $value) . "'";
    }
    return "'" . $connection->real_escape_string($value) . "'";
}

function is_tolerable_error($message, $statement) {
    // Extract just the SQL error message (after the SQLSTATE code)
    if (preg_match('/:\s*(.+)$/', $message, $match)) {
        $message = $match[1];
    }
    
    $lowerMessage = strtolower($message);
    
    // ALTER TABLE ADD column - can have various formats after COLUMN keyword removal
    if (strpos($lowerMessage, 'duplicate column') !== false && preg_match('/ALTER\s+TABLE\s+\w+\s+ADD\s+\w+/i', $statement)) {
        return true;
    }
    if (strpos($lowerMessage, 'duplicate index') !== false && preg_match('/CREATE\s+INDEX/i', $statement)) {
        return true;
    }
    if (strpos($lowerMessage, 'already exists') !== false && preg_match('/CREATE\s+TABLE/i', $statement)) {
        return true;
    }
    if (strpos($lowerMessage, 'unique constraint') !== false && preg_match('/INSERT\s+INTO/i', $statement)) {
        return true;
    }
    return false;
}

function execute_sql($sql, $connection, $db_type) {
    if ($db_type === 'sqlite') {
        // Split into individual statements for better error handling
        // This handles cases where one statement fails but others should proceed
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
        
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt) || $stmt[0] === '-') {
                continue; // Skip empty and comment-only statements
            }
            
            try {
                $connection->exec($stmt . ';');
            } catch (PDOException $e) {
                $message = $e->getMessage();
                if (!is_tolerable_error($message, $stmt)) {
                    throw new RuntimeException('SQLite error: ' . $message);
                }
                // If tolerable, just continue
                continue;
            }
            
            // Check for errors after exec (some DB adapters don't throw exceptions for certain errors)
            $errorInfo = $connection->errorInfo();
            if ($errorInfo[0] !== '00000' && $errorInfo[0] !== null) {
                $message = $errorInfo[2] ?? $errorInfo[1] ?? 'Unknown SQLite error';
                
                if (!is_tolerable_error($message, $stmt)) {
                    throw new RuntimeException('SQLite error: ' . $message);
                }
            }
        }
        return true;
    }

    if ($db_type === 'mysql') {
        if (!$connection->multi_query($sql)) {
            throw new RuntimeException('MySQL error: ' . $connection->error);
        }

        do {
            if ($result = $connection->store_result()) {
                $result->free();
            }
        } while ($connection->more_results() && $connection->next_result());

        return true;
    }

    throw new RuntimeException('Unsupported database type: ' . $db_type);
}

function translate_sql_for_sqlite_migration($sql) {
    // Convert MySQL-escaped strings to SQLite format (\' -> '')
    $sql = str_replace("\\'", "''", $sql);
    // Convert escaped line breaks
    $sql = str_replace('\\r\\n', "\n", $sql);
    $sql = str_replace('\\n', "\n", $sql);
    $sql = str_replace('\\t', "\t", $sql);
    
    // Remove MySQL SET statements (e.g., SET FOREIGN_KEY_CHECKS = 0;)
    $sql = preg_replace('/^\s*SET\s+[A-Za-z_]+\s*=\s*[^;]+;/m', '', $sql);
    
    $sql = preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bINT\s+PRIMARY\s+KEY\s+AUTOINCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bBIGINT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY', $sql);
    $sql = preg_replace('/\bINT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY', $sql);
    $sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);
    $sql = preg_replace('/\s*ENGINE=InnoDB\b/i', '', $sql);
    $sql = preg_replace('/\s*DEFAULT CHARSET=[^;\s)]+/i', '', $sql);
    $sql = preg_replace('/\s*COLLATE=[^;\s)]+/i', '', $sql);
    // Don't convert SET() data type to TEXT yet - it's a column constraint
    $sql = preg_replace('/\bON UPDATE CURRENT_TIMESTAMP\b/i', '', $sql);
    $sql = preg_replace('/\bSTART TRANSACTION\b/i', 'BEGIN TRANSACTION', $sql);
    $sql = preg_replace('/\bBEGIN TRANSACTION\b|\bBEGIN\b|\bCOMMIT\b|\bROLLBACK\b/i', '--', $sql);
    $sql = preg_replace('/\bENUM\s*\([^)]*\)/i', 'TEXT', $sql);
    // Convert SET column type (must come after removing statements)
    $sql = preg_replace('/\bSET\s*\([^)]*\)/i', 'TEXT', $sql);
    $sql = preg_replace('/\bLONGTEXT\b/i', 'TEXT', $sql);
    $sql = preg_replace('/\bAFTER\s+`?\w+`?/i', '', $sql);
    $sql = preg_replace('/\bIF NOT EXISTS\b/i', '', $sql);
    $sql = preg_replace('/ALTER TABLE\s+([^\s]+)\s+ADD COLUMN\s+/i', 'ALTER TABLE $1 ADD COLUMN ', $sql);
    // Convert ALTER TABLE ADD INDEX to CREATE INDEX
    $sql = preg_replace('/\bALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+INDEX\s+`?(\w+)`?\s*\(([^)]+)\)/i', 'CREATE INDEX IF NOT EXISTS $2 ON $1($3)', $sql);
    $sql = preg_replace('/ALTER TABLE\s+[^\s]+\s+ADD CONSTRAINT\s+`?\w+`?\s+FOREIGN KEY\s*\([^)]+\)\s+REFERENCES\s+[^\s]+\s*\([^)]+\)\s*(ON DELETE\s+[^;]+)?/i', '', $sql);
    $sql = preg_replace('/\bCOMMENT\s+\'[^\']*\'/i', '', $sql);
    $sql = preg_replace('/\bUNIQUE\s+KEY\s+`?\w+`?\s*\(([^)]+)\)/i', 'UNIQUE ($1)', $sql);
    $sql = preg_replace('/\(([A-Za-z0-9_]+)\([0-9]+\)\)/i', '($1)', $sql);
    $sql = preg_replace('/,\s*INDEX\s+`?\w+`?\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/\s*INDEX\s+`?\w+`?\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/,\s*KEY\s+`?\w+`?\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/\s*KEY\s+`?\w+`?\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/\bVARCHAR\([0-9]+\)/i', 'TEXT', $sql);
    $sql = preg_replace('/\bDELIMITER\b[\s\S]*$/i', '', $sql);
    $sql = preg_replace('/\bTINYINT\([0-9]+\)/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bTINYINT\b/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bDOUBLE\b/i', 'REAL', $sql);
    $sql = preg_replace('/\bFLOAT\b/i', 'REAL', $sql);
    $sql = str_replace('`', '', $sql);

    if (stripos($sql, 'ALTER TABLE') !== false && stripos($sql, 'ADD COLUMN') !== false && preg_match('/ALTER\s+TABLE\s+([^\s]+)\s+/i', $sql, $match)) {
        $tableName = $match[1];
        $sql = preg_replace('/,\s*ADD\s+COLUMN\s+/i', '; ALTER TABLE ' . $tableName . ' ADD COLUMN ', $sql);
    }

    return trim($sql);
}

function ensure_schema_migrations_table($connection, $db_type) {
    if ($db_type === 'sqlite') {
        $ddl = <<<SQL
CREATE TABLE IF NOT EXISTS schema_migrations (
    migration TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL
);
SQL;
        execute_sql($ddl, $connection, $db_type);
        return;
    }

    $ddl = <<<SQL
CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    execute_sql($ddl, $connection, $db_type);
}

function fetch_applied_migrations($connection, $db_type) {
    $applied = [];
    if ($db_type === 'sqlite') {
        $stmt = $connection->query('SELECT migration FROM schema_migrations');
        if ($stmt !== false) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $applied[] = $row['migration'];
            }
        }
    } else {
        $result = $connection->query('SELECT migration FROM schema_migrations');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $applied[] = $row['migration'];
            }
            $result->close();
        }
    }
    return array_flip($applied);
}

function record_migration($connection, $db_type, $migrationName) {
    $appliedAt = date('Y-m-d H:i:s');
    if ($db_type === 'sqlite') {
        $sql = 'INSERT INTO schema_migrations (migration, applied_at) VALUES (' . quote_value($migrationName, $connection, $db_type) . ', ' . quote_value($appliedAt, $connection, $db_type) . ');';
        execute_sql($sql, $connection, $db_type);
        return;
    }

    $stmt = $connection->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (?, ?)');
    if (!$stmt) {
        throw new RuntimeException('MySQL prepare error: ' . $connection->error);
    }

    $stmt->bind_param('ss', $migrationName, $appliedAt);
    if (!$stmt->execute()) {
        throw new RuntimeException('MySQL execute error: ' . $stmt->error);
    }
    $stmt->close();
}

log_cli('Running pending migrations from: ' . $migrationDir);
log_cli('Database type: ' . $db_type);
log_cli($dryRun ? 'Dry run mode enabled' : 'Applying pending migrations');
log_cli('');

ensure_schema_migrations_table($connection, $db_type);
$applied = fetch_applied_migrations($connection, $db_type);

$migrationFiles = [];
foreach (scandir($migrationDir) as $file) {
    if ($file === '.' || $file === '..' || $file === $scriptName) {
        continue;
    }
    if (!preg_match($pattern, $file)) {
        continue;
    }
    $migrationFiles[] = $file;
}
sort($migrationFiles, SORT_NATURAL | SORT_FLAG_CASE);

$pending = [];
foreach ($migrationFiles as $file) {
    if (!isset($applied[$file])) {
        $pending[] = $file;
    }
}

if (empty($pending)) {
    log_cli('No pending migrations found.');
    exit(0);
}

foreach ($pending as $migrationFile) {
    log_cli('Pending migration: ' . $migrationFile);
}

if ($dryRun) {
    exit(0);
}

foreach ($pending as $migrationFile) {
    $path = $migrationDir . DIRECTORY_SEPARATOR . $migrationFile;
    log_cli(PHP_EOL . 'Applying migration: ' . $migrationFile);
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    try {
        if ($extension === 'sql') {
            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new RuntimeException('Unable to read file: ' . $path);
            }
            if ($db_type === 'sqlite') {
                $sql = translate_sql_for_sqlite_migration($sql);
            }
            execute_sql($sql, $connection, $db_type);
        } else {
            ob_start();
            include $path;
            ob_end_clean();
        }
        record_migration($connection, $db_type, $migrationFile);
        log_cli('Successfully applied: ' . $migrationFile);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

log_cli('');
log_cli('All pending migrations applied successfully.');
