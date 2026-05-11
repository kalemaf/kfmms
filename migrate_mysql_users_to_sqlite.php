<?php
// Migrate users from MySQL to SQLite only
$mysql_host = '127.0.0.1';
$mysql_user = 'root';
$mysql_pass = 'Kalemaf123@@';
$mysql_db = 'maintenix';
$sqlite_file = __DIR__ . '/database/maintenix.db';

try {
    $mysql = new PDO("mysql:host=$mysql_host;dbname=$mysql_db;charset=utf8mb4", $mysql_user, $mysql_pass);
    $mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sqlite = new PDO("sqlite:$sqlite_file");
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure company_id exists in SQLite users table for compatibility
    $existing = [];
    $stmt = $sqlite->query("PRAGMA table_info('users')");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[] = $row['name'];
    }
    if (!in_array('company_id', $existing, true)) {
        $sqlite->exec("ALTER TABLE users ADD COLUMN company_id INTEGER DEFAULT NULL");
        echo "Added company_id to SQLite users table\n";
    }

    // Remove existing SQLite users to avoid duplicates
    $sqlite->exec("DELETE FROM users");
    echo "Cleared existing SQLite users\n";

    $mysqlUsers = $mysql->query("SELECT user_id, company_id, username, email, password_hash, phone, role, is_active, is_locked, failed_login_attempts, lockout_until, password_changed_at, password_expires_at, last_login_at, created_at, updated_at FROM users")->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($mysqlUsers) . " users in MySQL\n";

    if (count($mysqlUsers) === 0) {
        echo "No users to migrate.\n";
        exit(0);
    }

    $columns = array_keys($mysqlUsers[0]);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $insert = $sqlite->prepare("INSERT INTO users (" . implode(',', $columns) . ") VALUES ($placeholders)");

    $migrated = 0;
    foreach ($mysqlUsers as $row) {
        $values = array_values($row);
        // Convert empty strings or NULL-safe values
        foreach ($values as $k => $value) {
            if ($value === false) {
                $values[$k] = null;
            }
        }
        $insert->execute($values);
        $migrated++;
    }

    echo "Migrated $migrated users to SQLite successfully.\n";

    $count = $sqlite->query("SELECT COUNT(*) AS c FROM users")->fetch(PDO::FETCH_ASSOC)['c'];
    echo "SQLite users count: $count\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
