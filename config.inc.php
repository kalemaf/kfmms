<?php
/**
 * Main Configuration File for CMMS Application
 * Contains database connection settings and global configuration
 */

// ============================================================================
// ENVIRONMENT / CONFIGURATION LOADER
// ============================================================================

/**
 * Load environment variables from a .env file into $_ENV and getenv().
 *
 * @param string $path Path to .env file
 */
function load_dotenv($path) {
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value === 'true' || $value === 'TRUE') {
            $value = 'true';
        } elseif ($value === 'false' || $value === 'FALSE') {
            $value = 'false';
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
        putenv("$key=$value");
    }
}

/**
 * Get an env value from getenv(), $_ENV, or fallback.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        if (array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
        }
    }
    return $value !== false && $value !== null ? $value : $default;
}

/**
 * Parse boolean-like values from environment strings.
 *
 * @param mixed $value
 * @param bool $default
 * @return bool
 */
function parse_bool($value, $default = false) {
    if ($value === null) {
        return $default;
    }
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower(trim((string)$value));
    if ($normalized === '1' || $normalized === 'true' || $normalized === 'yes' || $normalized === 'on') {
        return true;
    }
    if ($normalized === '0' || $normalized === 'false' || $normalized === 'no' || $normalized === 'off') {
        return false;
    }
    return $default;
}

/**
 * Parse integer-like values from environment strings.
 *
 * @param mixed $value
 * @param int $default
 * @return int
 */
function parse_int($value, $default = 0) {
    if ($value === null || $value === '') {
        return $default;
    }
    return (int)$value;
}

load_dotenv(__DIR__ . '/.env');

// Database connection settings
$db_type = env('DB_TYPE', 'sqlite');  // 'mysql', 'sqlite', or 'none'
$db_port = parse_int(env('DB_PORT', '3306'), 3306);
$db_charset = env('DB_CHARSET', 'utf8mb4');

// MySQL settings (if using MySQL)
$hostName = env('DB_HOST', '127.0.0.1');
$userName = env('DB_USER', env('DB_USERNAME', 'root'));
$password = env('DB_PASS', env('DB_PASSWORD', ''));
$databaseName = env('DB_NAME', env('DB_DATABASE', 'maintenix'));

// SQLite settings (if using SQLite)
$db_file = env('DB_FILE', __DIR__ . '/database/maintenix.db');

// Log and backup configuration
$log_dir = env('LOG_DIR', __DIR__ . '/logs');
$log_retention_days = parse_int(env('LOG_RETENTION_DAYS', '30'), 30);
$backup_dir = env('BACKUP_DIR', __DIR__ . '/backups');
$backup_retention_days = parse_int(env('BACKUP_RETENTION_DAYS', '30'), 30);

// Payment integration configuration
$payment_provider = strtolower(env('PAYMENT_PROVIDER', 'manual'));
$stripe_secret_key = env('STRIPE_SECRET_KEY', '');
$stripe_publishable_key = env('STRIPE_PUBLISHABLE_KEY', '');
$payment_currency = strtoupper(env('PAYMENT_CURRENCY', 'USD'));
$app_url = env('APP_URL', 'http://127.0.0.1:8000');
$stripe_success_url = env('STRIPE_SUCCESS_URL', $app_url . '/license_gate.php?after_payment=1');
$stripe_cancel_url = env('STRIPE_CANCEL_URL', $app_url . '/license_gate.php');
$stripe_webhook_secret = env('STRIPE_WEBHOOK_SECRET', '');
$paypal_client_id = env('PAYPAL_CLIENT_ID', '');
$paypal_secret = env('PAYPAL_SECRET', '');
$paypal_environment = strtolower(env('PAYPAL_ENVIRONMENT', 'live'));
$paypal_success_url = env('PAYPAL_SUCCESS_URL', $app_url . '/paypal_return.php');
$paypal_cancel_url = env('PAYPAL_CANCEL_URL', $app_url . '/license_gate.php');
$paypal_merchant_name = env('PAYPAL_MERCHANT_NAME', 'Efficraft Technologies Limited');
$paypal_webhook_id = env('PAYPAL_WEBHOOK_ID', '');
$payment_notification_email = env('PAYMENT_NOTIFICATION_EMAIL', 'kalemaf876@gmail.com');
$app_env = strtolower(env('APP_ENV', 'production'));
$app_secret = env('APP_SECRET', '');
$debug_pages_enabled = parse_bool(env('ENABLE_DEBUG_PAGES', 'false'), false);

if ($db_type === 'sqlite') {
    if (!preg_match('/^(?:[A-Za-z]:|[\/\\\\])/', $db_file)) {
        $db_file = __DIR__ . DIRECTORY_SEPARATOR . $db_file;
    }
    $dbDir = dirname($db_file);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
}

if ($app_env === 'production' && file_exists(__DIR__ . '/.env')) {
    error_log('[SECURITY] .env file detected in production environment. Use environment variables instead of a .env file.');
}

// ============================================================================
// FALLBACK MOCK DATABASE CLASSES FOR DEVELOPMENT
// ============================================================================
// These classes are used when SQLite3 extension is not available in development mode

/*
class MockResult {
    private $users = [
        'admin' => ['user_id' => 1, 'username' => 'admin', 'email' => 'admin@cmms.local', 'password_hash' => '$2y$10$mYCBe.EgZsyqaIkvIRNKC.PRVW0hSn2KOp8xY3uLLz6af6kvLYveK', 'role' => 'admin', 'is_active' => 1, 'is_locked' => 0],
        'manager' => ['user_id' => 2, 'username' => 'manager', 'email' => 'manager@cmms.local', 'password_hash' => '$2y$10$mYCBe.EgZsyqaIkvIRNKC.PRVW0hSn2KOp8xY3uLLz6af6kvLYveK', 'role' => 'maintenance manager', 'is_active' => 1, 'is_locked' => 0],
        'tech' => ['user_id' => 3, 'username' => 'tech', 'email' => 'tech@cmms.local', 'password_hash' => '$2y$10$ray0i1QqxOdTy0IqXBZEcOL3Z8dIVD6.qM0P.xrD45DSKorknQmCe', 'role' => 'technician', 'is_active' => 1, 'is_locked' => 0],
        'operator' => ['user_id' => 4, 'username' => 'operator', 'email' => 'operator@cmms.local', 'password_hash' => '$2y$10$G4XTo5/djLTqwJeYrrAqv.hMKFNCvElZZjgEtUcs0tuWcGeeH4mxO', 'role' => 'operator', 'is_active' => 1, 'is_locked' => 0],
    ];
    private $search_term = '';
    private $index = 0;
    
    public function __construct($search = '') {
        $this->search_term = strtolower($search);
    }
    
    public function fetch_assoc() {
        if ($this->index === 0) {
            $this->index++;
            // Find user by username or email
            foreach ($this->users as $key => $user) {
                if ($this->search_term === '' || 
                    strtolower($user['username']) === $this->search_term || 
                    strtolower($user['email']) === $this->search_term) {
                    return $user;
                }
            }
        }
        return null;
    }
}
*/

/*
class MockStatement {
    private $query = '';
    private $search_term = '';
    
    public function __construct($query) {
        $this->query = $query;
    }
    
    public function bind_param($types, ...$params) {
        if (!empty($params)) {
            $this->search_term = $params[0];
        }
    }
    
    public function execute() {
        return true;
    }
    
    public function get_result() {
        if (strpos($this->query, 'SELECT') !== false && strpos($this->query, 'users') !== false) {
            return new MockResult($this->search_term);
        }
        return null;
    }
    
    public function close() {}
}
*/

// ============================================================================
// SQLITE PDO WRAPPER FOR COMPATIBILITY
// ============================================================================

class SQLiteStmt {
    private $pdoStmt;
    private $params = [];
    private $boundResultVars = [];

    public function __construct($pdoStmt) {
        $this->pdoStmt = $pdoStmt;
    }

    public function bind_param($types, ...$vars) {
        $this->params = $vars;
        foreach ($vars as $i => $var) {
            $this->pdoStmt->bindValue($i + 1, $vars[$i]);
        }
    }

    public function bindParam($param, &$var, $type = PDO::PARAM_STR) {
        return $this->pdoStmt->bindParam($param, $var, $type);
    }

    public function bindValue($param, $value, $type = PDO::PARAM_STR) {
        return $this->pdoStmt->bindValue($param, $value, $type);
    }

    public function bind_result(&...$vars) {
        $this->boundResultVars = &$vars;
    }

    public function execute($params = null) {
        return $this->pdoStmt->execute($params);
    }

    public function fetch($fetchMode = PDO::FETCH_ASSOC) {
        if (!empty($this->boundResultVars)) {
            $row = $this->pdoStmt->fetch(PDO::FETCH_NUM);
            if ($row === false) {
                return false;
            }
            foreach ($this->boundResultVars as $i => &$var) {
                $var = $row[$i] ?? null;
            }
            return true;
        }
        $row = $this->pdoStmt->fetch($fetchMode);
        return $row === false ? false : $row;
    }

    public function fetchAll($fetchMode = PDO::FETCH_ASSOC) {
        return $this->pdoStmt->fetchAll($fetchMode);
    }

    public function closeCursor() {
        return $this->pdoStmt->closeCursor();
    }

    public function get_result() {
        // For SELECT, return a result wrapper
        $this->pdoStmt->execute();
        return new SQLiteResult($this->pdoStmt);
    }

    public function store_result() {
        $this->pdoStmt->execute();
        return true;
    }

    public function num_rows() {
        $result = $this->get_result();
        return $result->num_rows();
    }

    public function close() {
        // PDO doesn't need close
    }

    public function rowCount() {
        return $this->pdoStmt->rowCount();
    }
}

class SQLiteResult {
    private $pdoStmt;
    private ?array $rows = null;
    private int $position = 0;

    public function __construct($pdoStmt) {
        $this->pdoStmt = $pdoStmt;
    }

    private function ensureAllRows(): void {
        if ($this->rows !== null) {
            return;
        }
        $this->rows = $this->pdoStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($this->rows)) {
            $this->rows = [];
        }
        $this->position = 0;
    }

    public function fetch_assoc() {
        if ($this->rows === null) {
            $row = $this->pdoStmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        }
        if ($this->position >= count($this->rows)) {
            return null;
        }
        return $this->rows[$this->position++];
    }

    public function fetch($fetchMode = PDO::FETCH_ASSOC) {
        if ($this->rows === null) {
            $row = $this->pdoStmt->fetch($fetchMode);
            return $row === false ? false : $row;
        }
        if ($this->position >= count($this->rows)) {
            return false;
        }
        return $this->rows[$this->position++];
    }

    public function fetchAll($fetchMode = PDO::FETCH_ASSOC) {
        $this->ensureAllRows();
        $remaining = array_slice($this->rows, $this->position);
        $this->position = count($this->rows);
        return $remaining;
    }

    public function fetch_all($fetchMode = PDO::FETCH_ASSOC) {
        return $this->fetchAll($fetchMode);
    }

    public function num_rows() {
        $this->ensureAllRows();
        return count($this->rows);
    }

    public function rowCount() {
        return $this->num_rows();
    }

    public function close() {
        // PDO doesn't need close
    }
}

class SQLitePDO extends PDO {
    public function __construct(string $dsn) {
        parent::__construct($dsn);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->exec('PRAGMA foreign_keys = ON;');
    }

    public static function translateSqlForSqlite(string $sql): string {
        if (stripos($sql, 'NOW()') === false && stripos($sql, 'CURDATE()') === false && stripos($sql, 'DATE_ADD') === false && stripos($sql, 'DATE_SUB') === false && stripos($sql, 'DATE_FORMAT') === false && stripos($sql, 'GREATEST') === false && stripos($sql, 'LEAST') === false && stripos($sql, 'TIMESTAMPDIFF') === false && stripos($sql, 'CONCAT') === false && stripos($sql, 'IFNULL') === false) {
            return $sql;
        }

        $sql = preg_replace('/\bNOW\(\)/i', 'CURRENT_TIMESTAMP', $sql);
        $sql = preg_replace('/\bCURDATE\(\)/i', "date('now')", $sql);

        // Handle IFNULL function - convert to COALESCE
        $sql = preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $sql);

        // Handle CONCAT function - convert to SQLite ||
        $sql = preg_replace('/\bCONCAT\s*\(\s*IFNULL\s*\(\s*([^,]+)\s*,\s*([^)]+)\s*\)\s*,\s*([^,]+)\s*,\s*([^)]+)\s*\)/i', '(COALESCE($1, $2) || $3 || $4)', $sql);

        // Handle remaining IFNULL
        $sql = preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $sql);

        $sql = preg_replace_callback('/\bDATE_ADD\s*\(\s*([^,]+?)\s*,\s*INTERVAL\s+([0-9]+)\s+([A-Z]+)\s*\)/i', function ($matches) {
            $expr = trim($matches[1]);
            $value = intval($matches[2]);
            $unit = strtolower($matches[3]);
            switch ($unit) {
                case 'day':
                case 'days':
                    return "date($expr, '+{$value} day')";
                case 'month':
                case 'months':
                    return "date($expr, '+{$value} month')";
                case 'year':
                case 'years':
                    return "date($expr, '+{$value} year')";
                case 'hour':
                case 'hours':
                    return "datetime($expr, '+{$value} hour')";
                case 'minute':
                case 'minutes':
                    return "datetime($expr, '+{$value} minute')";
                case 'second':
                case 'seconds':
                    return "datetime($expr, '+{$value} second')";
            }
            return $matches[0];
        }, $sql);

        $sql = preg_replace_callback('/\bDATE_SUB\s*\(\s*([^,]+?)\s*,\s*INTERVAL\s+([0-9]+)\s+([A-Z]+)\s*\)/i', function ($matches) {
            $expr = trim($matches[1]);
            $value = intval($matches[2]);
            $unit = strtolower($matches[3]);
            switch ($unit) {
                case 'day':
                case 'days':
                    return "date($expr, '-{$value} day')";
                case 'month':
                case 'months':
                    return "date($expr, '-{$value} month')";
                case 'year':
                case 'years':
                    return "date($expr, '-{$value} year')";
                case 'hour':
                case 'hours':
                    return "datetime($expr, '-{$value} hour')";
                case 'minute':
                case 'minutes':
                    return "datetime($expr, '-{$value} minute')";
                case 'second':
                case 'seconds':
                    return "datetime($expr, '-{$value} second')";
            }
            return $matches[0];
        }, $sql);

        $sql = preg_replace_callback('/\bDATE_FORMAT\s*\(\s*([^,]+?)\s*,\s*\'([^\']*)\'\s*\)/i', function ($matches) {
            $expr = trim($matches[1]);
            $format = $matches[2];
            $pattern = str_replace(
                ['%Y', '%y', '%m', '%c', '%d', '%e', '%H', '%h', '%i', '%s', '%T'],
                ['%Y', '%y', '%m', '%m', '%d', '%d', '%H', '%I', '%M', '%S', '%H:%M:%S'],
                $format
            );
            return "strftime('{$pattern}', {$expr})";
        }, $sql);

        $sql = preg_replace_callback('/\bGREATEST\s*\(([^)]+)\)/i', function ($matches) {
            return 'max(' . $matches[1] . ')';
        }, $sql);

        $sql = preg_replace_callback('/\bLEAST\s*\(([^)]+)\)/i', function ($matches) {
            return 'min(' . $matches[1] . ')';
        }, $sql);

        $sql = preg_replace_callback('/\bTIMESTAMPDIFF\s*\(\s*SECOND\s*,\s*([^,]+?)\s*,\s*([^)]+?)\s*\)/i', function ($matches) {
            return '(CAST((julianday(' . trim($matches[2]) . ') - julianday(' . trim($matches[1]) . ')) * 86400 AS INTEGER))';
        }, $sql);

        $sql = preg_replace_callback('/\bTIMESTAMPDIFF\s*\(\s*DAY\s*,\s*([^,]+?)\s*,\s*([^)]+?)\s*\)/i', function ($matches) {
            return '(CAST(julianday(' . trim($matches[2]) . ') - julianday(' . trim($matches[1]) . ') AS INTEGER))';
        }, $sql);

        return $sql;
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = []) {
        $query = self::translateSqlForSqlite($query);
        $pdoStmt = parent::prepare($query, $options);
        return $pdoStmt === false ? false : new SQLiteStmt($pdoStmt);
    }

    /** @suppress PHP2439 */
    #[\ReturnTypeWillChange]
    public function query($query, $fetchMode = null, ...$fetchModeArgs) {
        $query = self::translateSqlForSqlite($query);
        $result = parent::query($query, $fetchMode, ...$fetchModeArgs);
        if ($result === false) {
            return false;
        }
        return new SQLiteResult($result);
    }

    #[\ReturnTypeWillChange]
    public function exec(string $query) {
        $query = self::translateSqlForSqlite($query);
        return parent::exec($query);
    }

    #[\ReturnTypeWillChange]
    public function real_escape_string(string $string): string {
        // For PDO, use quote() but remove the surrounding quotes
        $quoted = $this->quote($string);
        return substr($quoted, 1, -1);
    }
}

/**
 * Ensure required SQLite schema columns exist for compatibility with newer app queries.
 *
 * @param SQLitePDO $pdo
 */
function ensure_sqlite_user_columns(SQLitePDO $pdo): void {
    try {
        $existing = [];
        $stmt = $pdo->query("PRAGMA table_info('users')");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[] = $row['name'];
        }

        // Add missing columns
        if (!in_array('is_locked', $existing, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN is_locked BOOLEAN NOT NULL DEFAULT 0');
        }
        if (!in_array('last_login_at', $existing, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL');
        }
    } catch (Exception $e) {
        // If the users table is missing or SQLite is unavailable, do not block startup here.
    }
}

/**
 * Get the database-appropriate NOW() replacement function.
 * MySQL uses NOW(), SQLite uses CURRENT_TIMESTAMP.
 *
 * @return string
 */
function get_current_timestamp_sql() {
    global $db_type;
    return ($db_type === 'sqlite') ? 'CURRENT_TIMESTAMP' : 'NOW()';
}

/**
 * Get the database-appropriate current date expression.
 * MySQL uses CURDATE(), SQLite uses date('now').
 *
 * @return string
 */
function get_current_date_sql() {
    global $db_type;
    return ($db_type === 'sqlite') ? "date('now')" : 'CURDATE()';
}

/**
 * Ensure the work_orders table exists in SQLite database.
 * Creates the table if it doesn't exist, converting from MySQL schema.
 *
 * @param SQLitePDO $pdo
 */
function ensure_sqlite_work_orders_table(SQLitePDO $pdo): void {
    try {
        // Check if work_orders table exists
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='work_orders'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            // Create work_orders table with SQLite-compatible schema
            $create_sql = "
                CREATE TABLE work_orders (
                    wo_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    descriptive_text TEXT NOT NULL DEFAULT '',
                    audit_item INTEGER DEFAULT NULL,
                    requestor TEXT NOT NULL DEFAULT '',
                    approval TEXT NOT NULL DEFAULT '',
                    equipment TEXT NOT NULL DEFAULT '',
                    description TEXT NOT NULL,
                    action TEXT,
                    mechanic_id INTEGER DEFAULT NULL,
                    priority INTEGER NOT NULL DEFAULT 0,
                    request_id INTEGER DEFAULT NULL,
                    submit_date TEXT DEFAULT NULL,
                    est_hours INTEGER DEFAULT NULL,
                    act_hours INTEGER DEFAULT NULL,
                    account TEXT DEFAULT NULL,
                    complete_date TEXT DEFAULT NULL,
                    coordinating_instructions TEXT,
                    needed_date TEXT DEFAULT NULL,
                    wo_status TEXT NOT NULL DEFAULT '',
                    inspected_by TEXT NOT NULL DEFAULT '',
                    maintenance_type TEXT DEFAULT NULL,
                    failure_mode TEXT DEFAULT NULL,
                    updated TEXT DEFAULT CURRENT_TIMESTAMP,
                    sla_due_date TEXT DEFAULT NULL,
                    down_time_hours REAL DEFAULT NULL,
                    response_time REAL DEFAULT NULL,
                    resolution_time REAL DEFAULT NULL,
                    tenant_id INTEGER NOT NULL DEFAULT 1
                )
            ";
            $pdo->exec($create_sql);
        } else {
            // Table exists, check for missing columns and add them
            $existing = [];
            $stmt = $pdo->query("PRAGMA table_info('work_orders')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }

            // Add missing columns
            if (!in_array('sla_due_date', $existing, true)) {
                $pdo->exec('ALTER TABLE work_orders ADD COLUMN sla_due_date TEXT DEFAULT NULL');
            }
            if (!in_array('down_time_hours', $existing, true)) {
                $pdo->exec('ALTER TABLE work_orders ADD COLUMN down_time_hours REAL DEFAULT NULL');
            }
            if (!in_array('response_time', $existing, true)) {
                $pdo->exec('ALTER TABLE work_orders ADD COLUMN response_time REAL DEFAULT NULL');
            }
            if (!in_array('resolution_time', $existing, true)) {
                $pdo->exec('ALTER TABLE work_orders ADD COLUMN resolution_time REAL DEFAULT NULL');
            }
            if (!in_array('request_id', $existing, true)) {
                $pdo->exec('ALTER TABLE work_orders ADD COLUMN request_id INTEGER DEFAULT NULL');
            }
            if (!in_array('maintenance_type', $existing, true)) {
                $pdo->exec('ALTER TABLE work_orders ADD COLUMN maintenance_type TEXT DEFAULT NULL');
            }
            if (!in_array('failure_mode', $existing, true)) {
                $pdo->exec('ALTER TABLE work_orders ADD COLUMN failure_mode TEXT DEFAULT NULL');
            }
            if (!in_array('tenant_id', $existing, true)) {
                $pdo->exec('ALTER TABLE work_orders ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1');
            }
        }
    } catch (Exception $e) {
        // If table creation/modification fails, do not block startup here.
    }
}

function ensure_sqlite_work_order_requests_table(SQLitePDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='work_order_requests'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $create_sql = "
                CREATE TABLE work_order_requests (
                    request_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    requestor_user_id INTEGER DEFAULT NULL,
                    descriptive_text TEXT NOT NULL DEFAULT '',
                    requestor TEXT NOT NULL DEFAULT '',
                    equipment INTEGER DEFAULT NULL,
                    description TEXT NOT NULL DEFAULT '',
                    priority INTEGER NOT NULL DEFAULT 1,
                    submit_date TEXT DEFAULT NULL,
                    needed_date TEXT DEFAULT NULL,
                    sla_due_date TEXT DEFAULT NULL,
                    status TEXT NOT NULL DEFAULT 'Pending Approval',
                    approval_by_id INTEGER DEFAULT NULL,
                    approval_date TEXT DEFAULT NULL,
                    approval_notes TEXT DEFAULT NULL,
                    work_order_id INTEGER DEFAULT NULL,
                    updated TEXT DEFAULT CURRENT_TIMESTAMP,
                    tenant_id INTEGER NOT NULL DEFAULT 1
                )
            ";
            $pdo->exec($create_sql);
        } else {
            $existing = [];
            $stmt = $pdo->query("PRAGMA table_info('work_order_requests')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }

            $columnsToAdd = [
                'requestor_user_id' => 'INTEGER DEFAULT NULL',
                'equipment' => 'INTEGER DEFAULT NULL',
                'priority' => 'INTEGER NOT NULL DEFAULT 1',
                'submit_date' => 'TEXT DEFAULT NULL',
                'needed_date' => 'TEXT DEFAULT NULL',
                'sla_due_date' => 'TEXT DEFAULT NULL',
                'status' => "TEXT NOT NULL DEFAULT 'Pending Approval'",
                'approval_by_id' => 'INTEGER DEFAULT NULL',
                'approval_date' => 'TEXT DEFAULT NULL',
                'approval_notes' => 'TEXT DEFAULT NULL',
                'work_order_id' => 'INTEGER DEFAULT NULL',
                'updated' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
                'tenant_id' => 'INTEGER NOT NULL DEFAULT 1'
            ];

            foreach ($columnsToAdd as $column => $definition) {
                if (!in_array($column, $existing, true)) {
                    $pdo->exec("ALTER TABLE work_order_requests ADD COLUMN $column $definition");
                }
            }
        }
    } catch (Exception $e) {
        // If table creation/modification fails, do not block startup here.
    }
}

function ensure_sqlite_equipment_table(SQLitePDO $pdo): void {
    try {
        // Check if equipment table exists
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='equipment'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            // Create equipment table with SQLite-compatible schema
            $create_sql = "
                CREATE TABLE equipment (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    parent_id INTEGER NOT NULL DEFAULT 0,
                    description VARCHAR(20) NOT NULL DEFAULT '',
                    location VARCHAR(100) NOT NULL DEFAULT '',
                    status VARCHAR(50) NOT NULL DEFAULT '',
                    manufacturer VARCHAR(100) NOT NULL DEFAULT '',
                    model VARCHAR(100) NOT NULL DEFAULT '',
                    serial_number VARCHAR(100) NOT NULL DEFAULT '',
                    photo VARCHAR(255) NOT NULL DEFAULT '',
                    tenant_id INTEGER NOT NULL DEFAULT 1
                )
            ";
            $pdo->exec($create_sql);
        } else {
            // Table exists, check for missing columns and add them
            $existing = [];
            $stmt = $pdo->query("PRAGMA table_info('equipment')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }

            // Add missing columns
            if (!in_array('location', $existing, true)) {
                $pdo->exec("ALTER TABLE equipment ADD COLUMN location VARCHAR(100) NOT NULL DEFAULT ''");
            }
            if (!in_array('status', $existing, true)) {
                $pdo->exec("ALTER TABLE equipment ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT ''");
            }
            if (!in_array('manufacturer', $existing, true)) {
                $pdo->exec("ALTER TABLE equipment ADD COLUMN manufacturer VARCHAR(100) NOT NULL DEFAULT ''");
            }
            if (!in_array('model', $existing, true)) {
                $pdo->exec("ALTER TABLE equipment ADD COLUMN model VARCHAR(100) NOT NULL DEFAULT ''");
            }
            if (!in_array('serial_number', $existing, true)) {
                $pdo->exec("ALTER TABLE equipment ADD COLUMN serial_number VARCHAR(100) NOT NULL DEFAULT ''");
            }
            if (!in_array('photo', $existing, true)) {
                $pdo->exec("ALTER TABLE equipment ADD COLUMN photo VARCHAR(255) NOT NULL DEFAULT ''");
            }
            if (!in_array('tenant_id', $existing, true)) {
                $pdo->exec("ALTER TABLE equipment ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 1");
            }
        }
    } catch (Exception $e) {
        // If table creation/modification fails, do not block startup here.
    }
}

function ensure_sqlite_equipment_spares_table(SQLitePDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='equipment_spares'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $create_sql = "
                CREATE TABLE equipment_spares (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    equipment_id INTEGER NOT NULL,
                    part_id INTEGER DEFAULT NULL,
                    part_name TEXT NOT NULL,
                    part_number TEXT DEFAULT '',
                    quantity INTEGER DEFAULT 0,
                    notes TEXT
                )";
            $pdo->exec($create_sql);
        } else {
            $existing = [];
            $stmt = $pdo->query("PRAGMA table_info('equipment_spares')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }
            if (!in_array('part_id', $existing, true)) {
                $pdo->exec('ALTER TABLE equipment_spares ADD COLUMN part_id INTEGER DEFAULT NULL');
            }
            if (!in_array('quantity', $existing, true)) {
                $pdo->exec('ALTER TABLE equipment_spares ADD COLUMN quantity INTEGER DEFAULT 0');
            }
            if (!in_array('notes', $existing, true)) {
                $pdo->exec('ALTER TABLE equipment_spares ADD COLUMN notes TEXT');
            }
        }
    } catch (Exception $e) {
        // If table creation/modification fails, do not block startup here.
    }
}

function ensure_sqlite_users_table(SQLitePDO $pdo): void {
    try {
        // Check if users table exists
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            // Create users table with SQLite-compatible schema
            $create_sql = "
                CREATE TABLE users (
                    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    email VARCHAR(255) UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    phone VARCHAR(255),
                    role VARCHAR(20) NOT NULL DEFAULT 'operator',
                    is_active INTEGER NOT NULL DEFAULT 1,
                    is_locked INTEGER NOT NULL DEFAULT 0,
                    failed_login_attempts INTEGER NOT NULL DEFAULT 0,
                    lockout_until TEXT,
                    password_changed_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    password_expires_at TEXT,
                    last_login_at TEXT,
                    must_change_password INTEGER NOT NULL DEFAULT 0,
                    temporary_password VARCHAR(255),
                    password_generated_at TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $pdo->exec($create_sql);
        } else {
            // Table exists, check for missing columns and add them
            $existing = [];
            $stmt = $pdo->query("PRAGMA table_info('users')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }

            // Add missing columns
            if (!in_array('is_locked', $existing, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN is_locked INTEGER NOT NULL DEFAULT 0");
            }
            if (!in_array('last_login_at', $existing, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN last_login_at TEXT");
            }
            if (!in_array('failed_login_attempts', $existing, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN failed_login_attempts INTEGER NOT NULL DEFAULT 0");
            }
            if (!in_array('lockout_until', $existing, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN lockout_until TEXT");
            }
            if (!in_array('password_changed_at', $existing, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN password_changed_at TEXT DEFAULT CURRENT_TIMESTAMP");
            }
            if (!in_array('password_expires_at', $existing, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN password_expires_at TEXT");
            }
            if (!in_array('phone', $existing, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(255)");
            }
            if (!in_array('must_change_password', $existing, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0");
            }
            if (!in_array('temporary_password', $existing, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN temporary_password VARCHAR(255)");
            }
            if (!in_array('password_generated_at', $existing, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN password_generated_at TEXT");
            }
        }
    } catch (Exception $e) {
        // If table creation/modification fails, do not block startup here.
    }
}

function ensure_sqlite_user_creation_authorizations_table(SQLitePDO $pdo): void {
    try {
        // Check if user_creation_authorizations table exists
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user_creation_authorizations'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            // Create user_creation_authorizations table with SQLite-compatible schema
            $create_sql = "
                CREATE TABLE user_creation_authorizations (
                    auth_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    pending_username VARCHAR(50) NOT NULL,
                    pending_email VARCHAR(255),
                    password_hash VARCHAR(255) NOT NULL,
                    temp_password VARCHAR(255),
                    role TEXT NOT NULL DEFAULT 'operator' CHECK(role IN ('admin','maintenance manager','supervisor','technician','operator')),
                    phone VARCHAR(20),
                    country_code VARCHAR(5) DEFAULT '+256',
                    company_id INTEGER,
                    requestor_id INTEGER,
                    requestor_name VARCHAR(255),
                    auth_code CHAR(6) NOT NULL UNIQUE,
                    is_used BOOLEAN NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME,
                    UNIQUE(auth_code)
                )
            ";
            $pdo->exec($create_sql);
        } else {
            // Table exists, check for missing columns and add them
            $existing = [];
            $stmt = $pdo->query("PRAGMA table_info('user_creation_authorizations')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }

            // Add missing columns if needed
            if (!in_array('temp_password', $existing, true)) {
                $pdo->exec('ALTER TABLE user_creation_authorizations ADD COLUMN temp_password VARCHAR(255)');
            }
            if (!in_array('used_at', $existing, true)) {
                $pdo->exec('ALTER TABLE user_creation_authorizations ADD COLUMN used_at DATETIME');
            }
            if (!in_array('company_id', $existing, true)) {
                $pdo->exec('ALTER TABLE user_creation_authorizations ADD COLUMN company_id INTEGER');
            }
        }
    } catch (Exception $e) {
        // If table creation/modification fails, do not block startup here.
    }
}

function ensure_sqlite_company_licenses_table(SQLitePDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='company_licenses'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $create_sql = "
                CREATE TABLE company_licenses (
                    license_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    company_id INTEGER NOT NULL,
                    license_key VARCHAR(255) NOT NULL UNIQUE,
                    purchased_seats INTEGER NOT NULL DEFAULT 0,
                    used_seats INTEGER NOT NULL DEFAULT 0,
                    license_type TEXT NOT NULL DEFAULT 'basic',
                    payment_term TEXT NOT NULL DEFAULT 'monthly',
                    expires_at TEXT,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $pdo->exec($create_sql);
            // Create indexes
            $pdo->exec('CREATE INDEX idx_company_licenses_company_id ON company_licenses(company_id)');
            $pdo->exec('CREATE INDEX idx_company_licenses_is_active ON company_licenses(is_active)');
            $pdo->exec('CREATE INDEX idx_company_licenses_license_key ON company_licenses(license_key)');
        } else {
            // Table exists, check for missing columns and add them
            $existing = [];
            $stmt = $pdo->query("PRAGMA table_info('company_licenses')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }

            // Add missing columns if needed
            if (!in_array('license_id', $existing, true)) {
                $pdo->exec('ALTER TABLE company_licenses ADD COLUMN license_id INTEGER PRIMARY KEY AUTOINCREMENT');
            }
            if (!in_array('created_at', $existing, true)) {
                $pdo->exec('ALTER TABLE company_licenses ADD COLUMN created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP');
            }
            if (!in_array('updated_at', $existing, true)) {
                $pdo->exec('ALTER TABLE company_licenses ADD COLUMN updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP');
            }
        }
    } catch (Exception $e) {
        error_log("Error ensuring company_licenses table: " . $e->getMessage());
    }
}

function ensure_sqlite_companies_table(SQLitePDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='companies'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $create_sql = "
                CREATE TABLE companies (
                    company_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    company_name VARCHAR(255) NOT NULL,
                    company_email VARCHAR(255),
                    contact_name VARCHAR(255),
                    contact_phone VARCHAR(50),
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $pdo->exec($create_sql);
            $pdo->exec('CREATE UNIQUE INDEX uk_company_name ON companies(company_name)');
            $pdo->exec('CREATE INDEX idx_is_active ON companies(is_active)');
        }
    } catch (Exception $e) {
        error_log("Error ensuring companies table: " . $e->getMessage());
    }
}

function ensure_sqlite_system_control_table(SQLitePDO $pdo): void {
    try {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='system_control'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $create_sql = "
                CREATE TABLE system_control (
                    control_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    company_id INTEGER NOT NULL UNIQUE,
                    system_activated INTEGER NOT NULL DEFAULT 0,
                    system_locked INTEGER NOT NULL DEFAULT 0,
                    activation_date TEXT,
                    feature_tier TEXT NOT NULL DEFAULT 'trial',
                    max_users INTEGER NOT NULL DEFAULT 5,
                    current_users INTEGER NOT NULL DEFAULT 0,
                    subscription_status TEXT NOT NULL DEFAULT 'trial',
                    subscription_expires_at TEXT,
                    system_version TEXT DEFAULT '1.0.0',
                    lock_reason TEXT,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $pdo->exec($create_sql);
            $pdo->exec('CREATE INDEX idx_company_id ON system_control(company_id)');
        } else {
            // Table exists, check for missing columns
            $existing = [];
            $stmt = $pdo->query("PRAGMA table_info('system_control')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[] = $row['name'];
            }

            // Add missing columns
            if (!in_array('lock_reason', $existing, true)) {
                $pdo->exec('ALTER TABLE system_control ADD COLUMN lock_reason TEXT');
            }
            if (!in_array('updated_at', $existing, true)) {
                $pdo->exec('ALTER TABLE system_control ADD COLUMN updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP');
            }
        }
    } catch (Exception $e) {
        error_log("Error ensuring system_control table: " . $e->getMessage());
    }
}

// Include inventory manager for table creation functions
require_once __DIR__ . '/libraries/inventory_manager.php';

// Include performance monitoring schema for SLA and performance tracking
require_once __DIR__ . '/libraries/performance_schema.php';

// Create database connection
$connection = null;
$db_error = '';

if ($db_type === 'sqlite') {
    try {
        $connection = new SQLitePDO("sqlite:$db_file");
        $connection->exec('PRAGMA busy_timeout = 30000;');
        $connection->exec('PRAGMA journal_mode = WAL;');
        ensure_sqlite_user_columns($connection);
        ensure_sqlite_work_orders_table($connection);
        ensure_sqlite_work_order_requests_table($connection);
        ensure_sqlite_equipment_table($connection);
        ensure_sqlite_equipment_spares_table($connection);
        ensure_sqlite_users_table($connection);
        ensure_sqlite_user_creation_authorizations_table($connection);
        ensure_sqlite_companies_table($connection);
        ensure_sqlite_company_licenses_table($connection);
        ensure_sqlite_system_control_table($connection);
        ensure_inventory_tables($connection);
        initialize_performance_monitoring_tables($connection);  // Initialize SLA and performance tables
        $c = $connection;  // Alias for compatibility
        $pdo = $connection;  // PDO-compatible alias for legacy pages
    } catch (Exception $e) {
        error_log("SQLite initialization error: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
        $db_error = 'SQLite connection failed: ' . $e->getMessage();
    }
} elseif ($db_type === 'mysql') {
    if (function_exists('mysqli_connect')) {
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }

        try {
            $connection = @new mysqli($hostName, $userName, $password, $databaseName, $db_port);
        } catch (Exception $e) {
            $connection = null;
            $db_error = 'MySQL connection failed: ' . $e->getMessage();
        }

        if ($connection !== null) {
            if ($connection->connect_error) {
                $db_error = 'MySQL connection failed: ' . $connection->connect_error;
                $connection = null;
            } else {
                $connection->set_charset($db_charset);
                $c = $connection;  // Alias for compatibility
                $pdo = $connection;  // PDO-compatible alias for legacy pages
            }
        }
    } else {
        $db_error = 'MySQLi extension not available';
    }
} else {
    // No database connection - application will run in limited mode
    $db_error = 'Database not configured - running in offline mode';
}

// Global flag to check if database is available
$db_available = ($connection !== null && empty($db_error));

// Only show database error on pages other than login/welcome/auth pages
// This prevents breaking the login UI while still logging errors
if (!$db_available && $app_env !== 'production') {
    $current_page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $login_pages = ['welcome.php', 'auth.php', 'license_gate.php', 'paypal_return.php'];
    $is_login_page = in_array($current_page, $login_pages, true);
    
    if (!$is_login_page && php_sapi_name() !== 'cli') {
        // Log the error for debugging
        error_log("[DATABASE ERROR] $db_error on page: $current_page");
        
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Database configuration error</title>' .
             '<style>body{font-family:Segoe UI,Arial,sans-serif;margin:40px;color:#222;}pre{background:#f7f7f7;border:1px solid #ddd;padding:20px;white-space:pre-wrap;word-break:break-word;}</style>' .
             '</head><body><h1>Database configuration error</h1>' .
             '<p><strong>Current DB_TYPE:</strong> ' . htmlspecialchars($db_type) . '</p>' .
             '<pre>' . htmlspecialchars($db_error) . '</pre>' .
             '<p>Install the missing PHP SQLite extension or update <code>.env</code> to use MySQL with valid credentials.</p>' .
             '</body></html>';
        exit;
    } elseif (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Database error: $db_error\n");
    }
}

// ============================================================================
// SESSION CONFIGURATION
// ============================================================================

// Session save path (relative to application root)
$session_save_path = env('SESSION_SAVE_PATH', __DIR__ . '/sessions');

$is_localhost = isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1|::1)(:\d+)?/', $_SERVER['HTTP_HOST']);
$force_https = parse_bool(env('FORCE_HTTPS', $is_localhost ? 'false' : 'true'), !$is_localhost);
if ($is_localhost && php_sapi_name() !== 'cli') {
    $force_https = false;
}

$session_cookie_secure = parse_bool(env('SESSION_COOKIE_SECURE', $force_https ? 'true' : 'false'), $force_https);
$session_cookie_httponly = parse_bool(env('SESSION_COOKIE_HTTPONLY', 'true'), true);
$session_cookie_samesite = env('SESSION_COOKIE_SAMESITE', 'Strict');

if (!$force_https) {
    $session_cookie_secure = false;
}

if ($app_env === 'production') {
    if ($debug_pages_enabled || parse_bool(env('DEBUG_MODE', 'false'), false) || parse_bool(env('DISPLAY_ERRORS', 'false'), false) || parse_bool(env('DEVELOPER_BYPASS_LICENSE', 'false'), false)) {
        error_log('[SECURITY] Production environment overriding unsafe debug and bypass settings.');
    }
    $session_cookie_secure = true;
$session_cookie_httponly = true;
    $session_cookie_samesite = 'Strict';
    $debug_pages_enabled = false;
}

// Start session BEFORE calling ini_set on session parameters
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
    // Only set ini values if this is the first session start
    @ini_set('session.use_strict_mode', 1);
    @ini_set('session.use_only_cookies', 1);
    @ini_set('session.cookie_secure', $session_cookie_secure ? 1 : 0);
    @ini_set('session.cookie_httponly', $session_cookie_httponly ? 1 : 0);

    // Set SameSite only for PHP 7.3+
    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        @ini_set('session.cookie_samesite', $session_cookie_samesite);
    }

    @ini_set('session.cookie_lifetime', 0);
    // Additional security settings
    @ini_set('session.gc_probability', 1);
    @ini_set('session.gc_divisor', 100);
    @ini_set('session.gc_maxlifetime', 3600);  // 1 hour inactive session timeout
    @ini_set('session.name', 'CMMS_SESSION');
    @ini_set('session.hash_function', 'sha256');
    @ini_set('session.sid_length', 32);

    if (!empty($session_save_path)) {
        @session_save_path($session_save_path);
    }
}

// ============================================================================
// SMTP EMAIL CONFIGURATION
// ============================================================================

// SMTP settings for email notifications
$SMTP_ENABLED = parse_bool(env('SMTP_ENABLED', 'true'), true);           // Set to true to enable SMTP
$SMTP_HOST = env('SMTP_HOST', 'smtp.gmail.com');  // SMTP server hostname
$SMTP_PORT = parse_int(env('SMTP_PORT', '587'), 587);               // SMTP port (587 for TLS, 465 for SSL, 25 for non-secure)
$SMTP_USER = env('SMTP_USER', '');                // SMTP username
$SMTP_PASS = env('SMTP_PASS', '');                // SMTP password
$SMTP_SECURE = env('SMTP_SECURE', 'tls');           // SMTP security: 'tls', 'ssl', or '' for none
$SMTP_FROM_EMAIL = env('SMTP_FROM_EMAIL', $SMTP_USER ?: 'no-reply@example.com');  // Default from email
$SMTP_FROM_NAME = env('SMTP_FROM_NAME', 'Maintenix');           // Default from name
function load_system_smtp_settings() {
    global $SMTP_ENABLED, $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;

    $fileCandidates = [
        __DIR__ . '/smtp_settings.json',
        __DIR__ . '/system_settings.json',
        __DIR__ . '/app_settings.json',
    ];

    foreach ($fileCandidates as $filePath) {
        if (!is_readable($filePath)) {
            continue;
        }

        $json = @file_get_contents($filePath);
        if ($json === false) {
            continue;
        }

        $settings = json_decode($json, true);
        if (!is_array($settings)) {
            continue;
        }

        $normalized = [];
        foreach ($settings as $key => $value) {
            $normalized[strtolower(trim((string)$key))] = $value;
        }

        if (array_key_exists('smtp_enabled', $normalized)) {
            $enabled = $normalized['smtp_enabled'];
            if (is_bool($enabled)) {
                $SMTP_ENABLED = $enabled;
            } elseif (is_numeric($enabled)) {
                $SMTP_ENABLED = ((int)$enabled !== 0);
            } else {
                $value = strtolower(trim((string)$enabled));
                $SMTP_ENABLED = in_array($value, ['1', 'true', 'yes', 'on'], true);
            }
        }
        if (array_key_exists('smtp_host', $normalized)) {
            $SMTP_HOST = trim((string)$normalized['smtp_host']);
        }
        if (array_key_exists('smtp_port', $normalized)) {
            $SMTP_PORT = (int)$normalized['smtp_port'];
        }
        if (array_key_exists('smtp_user', $normalized)) {
            $SMTP_USER = trim((string)$normalized['smtp_user']);
        }
        if (array_key_exists('smtp_pass', $normalized)) {
            $SMTP_PASS = trim((string)$normalized['smtp_pass']);
        }
        if (array_key_exists('smtp_secure', $normalized)) {
            $SMTP_SECURE = trim((string)$normalized['smtp_secure']);
        }
        if (array_key_exists('smtp_from_email', $normalized)) {
            $SMTP_FROM_EMAIL = trim((string)$normalized['smtp_from_email']);
        }
        if (array_key_exists('smtp_from_name', $normalized)) {
            $SMTP_FROM_NAME = trim((string)$normalized['smtp_from_name']);
        }

        break;
    }
}

load_system_smtp_settings();
// ============================================================================
// APPLICATION SETTINGS
// ============================================================================

$debug_mode = parse_bool(env('DEBUG_MODE', 'false'), false);
$display_errors = env('DISPLAY_ERRORS', $debug_mode ? 'true' : 'false');
$developer_bypass_license = parse_bool(env('DEVELOPER_BYPASS_LICENSE', 'false'), false);

if ($app_env === 'production') {
    if ($debug_mode || parse_bool($display_errors, false) || $developer_bypass_license) {
        error_log('[SECURITY] Production environment disabling debug_mode, display_errors, and developer bypass license mode.');
    }
    $debug_mode = false;
    $display_errors = 'false';
    $developer_bypass_license = false;
}

ini_set('display_errors', parse_bool($display_errors, false) ? 1 : 0);
ini_set('display_startup_errors', parse_bool($display_errors, false) ? 1 : 0);
error_reporting(E_ALL);

// Prevent mysqli from throwing exceptions; handle errors manually
mysqli_report(MYSQLI_REPORT_OFF);

// ============================================================================
// SECURITY SETTINGS
// ============================================================================

// File upload settings
$max_file_size = 10 * 1024 * 1024; // 10MB max file size
$allowed_file_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

// ============================================================================
// PATH SETTINGS
// ============================================================================

// Define application root path
define('APP_ROOT', __DIR__);
define('APP_ENV', $app_env);

// Upload directories - tenant-specific paths are handled by get_tenant_upload_path() function
$upload_path = APP_ROOT . '/uploads/'; // Legacy - use get_tenant_upload_path() for new code
$private_upload_path = APP_ROOT . '/private_attachments/'; // Legacy - use get_tenant_upload_path('attachments') for new code

// Ensure upload directories exist
if (!is_dir($upload_path)) {
    mkdir($upload_path, 0755, true);
}
if (!is_dir($private_upload_path)) {
    mkdir($private_upload_path, 0755, true);
}

// ============================================================================
// TIMEZONE SETTINGS
// ============================================================================

// Set default timezone
date_default_timezone_set('America/New_York');

// ============================================================================
// DEBUG SETTINGS (disable in production)
// ============================================================================

if (!isset($developer_bypass_license)) {
    $developer_bypass_license = parse_bool(env('DEVELOPER_BYPASS_LICENSE', 'false'), false);
}
if ($app_env === 'production') {
    $developer_bypass_license = false;
}

// ============================================================================
// SECURITY HEADERS
// ============================================================================

if (php_sapi_name() !== 'cli' && !headers_sent()) {
    // Only set restrictive frame options in production
    if ($app_env === 'production') {
        header('X-Frame-Options: SAMEORIGIN');
    }
    // In development, allow all frames to prevent iframe security issues
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    $https_request = (
        !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off'
    ) || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    // Determine if this is a localhost/development environment
    $is_localhost = isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1|::1)(:\d+)?/', $_SERVER['HTTP_HOST']);

    if ($force_https && !$https_request && !$is_localhost) {
        $redirect_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $redirect_url, true, 301);
        exit;
    }

    if ($https_request && !$is_localhost) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

?>