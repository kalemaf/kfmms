<?php
/**
 * Production Database Setup Script
 * Sets up database schema using migrations (no sample data) for clean production deployment
 */

require_once __DIR__ . '/config.inc.php';

if (PHP_SAPI !== 'cli') {
    echo "This script is CLI-only for security.\n";
    exit(1);
}

if (!$db_available) {
    fwrite(STDERR, "Database unavailable: {$db_error}\n");
    exit(1);
}

echo "Starting production database setup...\n";
echo "Database Type: {$db_type}\n\n";

$options = getopt('', ['help']);
$showHelp = isset($options['help']);

if ($showHelp) {
    echo "Usage: php setup_production_database.php\n";
    echo "This script sets up a clean production database using migrations.\n";
    exit(0);
}

// Check if database already has data
try {
    $tables_with_data = [];

    // Check a few key tables for existing data
    $check_tables = ['equipment', 'work_orders', 'users', 'parts_master'];

    foreach ($check_tables as $table) {
        if (table_exists($table)) {
            $count_sql = "SELECT COUNT(*) as count FROM {$table}";
            $stmt = $connection->query($count_sql);
            if ($stmt) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row['count'] > 0) {
                    $tables_with_data[] = $table . " ({$row['count']} records)";
                }
            }
        }
    }

    if (!empty($tables_with_data)) {
        echo "WARNING: Database already contains data in the following tables:\n";
        foreach ($tables_with_data as $table_info) {
            echo "  - {$table_info}\n";
        }
        echo "\nIf you want to start fresh, run: php clear_production_data.php\n";
        echo "Then re-run this setup script.\n\n";
        echo "Continuing with migration check...\n\n";
    }

} catch (Exception $e) {
    echo "Warning: Could not check existing data: " . $e->getMessage() . "\n\n";
}

// Run migrations to ensure schema is up to date
echo "Running database migrations...\n";

$migration_script = __DIR__ . '/migrations/run_pending_migrations.php';
$command = "php \"{$migration_script}\"";

echo "Executing: {$command}\n";

$exit_code = 0;
$output = system($command, $exit_code);

if ($exit_code !== 0) {
    echo "\nMigration failed with exit code: {$exit_code}\n";
    if (!empty($output)) {
        echo "Migration output:\n{$output}\n";
    }
    exit(1);
}

echo "\nMigrations completed successfully.\n";

// Ensure required SQLite columns exist (for SQLite databases)
if ($db_type === 'sqlite') {
    echo "\nEnsuring SQLite schema compatibility...\n";

    // Call the existing functions to ensure schema is complete
    ensure_sqlite_user_columns($connection);
    ensure_sqlite_work_orders_table($connection);

    echo "SQLite schema compatibility ensured.\n";
}

// Create default admin user if no users exist
echo "\nChecking for admin user...\n";

try {
    $user_count_sql = "SELECT COUNT(*) as count FROM users";
    $stmt = $connection->query($user_count_sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_count = $row['count'];

    if ($user_count == 0) {
        echo "No users found. Creating default admin user...\n";

        // Create default admin user
        $default_username = 'admin';
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $default_email = 'admin@example.com';

        $insert_sql = "INSERT INTO users (username, email, password_hash, role, is_active, created_at, updated_at) VALUES (?, ?, ?, 'admin', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

        $stmt = $connection->prepare($insert_sql);
        $stmt->execute([$default_username, $default_email, $default_password]);

        echo "Default admin user created:\n";
        echo "  Username: {$default_username}\n";
        echo "  Password: admin123\n";
        echo "  Email: {$default_email}\n";
        echo "\n⚠️  IMPORTANT: Change the default password immediately after first login!\n\n";
    } else {
        echo "Users already exist ({$user_count} total). Skipping admin user creation.\n";
    }

} catch (Exception $e) {
    echo "Warning: Could not check/create admin user: " . $e->getMessage() . "\n";
}

echo "\n🎉 Production database setup complete!\n";
echo "\nNext steps:\n";
echo "1. Configure your .env file with production settings\n";
echo "2. Set up web server and PHP\n";
echo "3. Test the application login\n";
echo "4. Change default admin password\n";
echo "5. Configure backup schedules\n";