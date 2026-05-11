<?php
/**
 * Migrate SaaS Tables to SQLite
 * Creates missing system_control, subscription_payments, system_analytics, system_updates tables
 */

require_once 'config.inc.php';

if (PHP_SAPI !== 'cli') {
    echo "This script is CLI-only for security.\n";
    exit(1);
}

if (!$db_available || $db_type !== 'sqlite') {
    echo "SQLite database not available or not configured.\n";
    exit(1);
}

echo "Starting SaaS tables migration to SQLite...\n\n";

$tables_created = 0;
$errors = 0;

// SaaS table definitions for SQLite
$saas_tables = [
    'system_control' => "
        CREATE TABLE IF NOT EXISTS system_control (
            control_id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            system_activated INTEGER NOT NULL DEFAULT 0,
            activation_date TEXT NULL,
            last_health_check TEXT NULL,
            system_locked INTEGER NOT NULL DEFAULT 0,
            lock_reason TEXT NULL,
            subscription_status TEXT NOT NULL DEFAULT 'trial',
            subscription_expires_at TEXT NULL,
            grace_period_until TEXT NULL,
            max_users INTEGER NOT NULL DEFAULT 5,
            current_users INTEGER NOT NULL DEFAULT 0,
            feature_tier TEXT NOT NULL DEFAULT 'trial',
            allowed_features TEXT NULL,
            system_version TEXT NOT NULL DEFAULT '0.04',
            update_available INTEGER NOT NULL DEFAULT 0,
            last_update_check TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
            UNIQUE(company_id)
        )
    ",
    
    'subscription_payments' => "
        CREATE TABLE IF NOT EXISTS subscription_payments (
            payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            currency TEXT NOT NULL DEFAULT 'USD',
            payment_date TEXT NOT NULL,
            payment_method TEXT NULL,
            transaction_id TEXT NULL,
            subscription_period_start TEXT NOT NULL,
            subscription_period_end TEXT NOT NULL,
            payment_status TEXT NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            processed_by INTEGER NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
            FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL
        )
    ",
    
    'system_analytics' => "
        CREATE TABLE IF NOT EXISTS system_analytics (
            analytics_id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            metric_type TEXT NOT NULL,
            metric_value REAL NOT NULL,
            metric_unit TEXT NULL,
            recorded_at TEXT NOT NULL,
            ip_address TEXT NULL,
            user_agent TEXT NULL,
            additional_data TEXT NULL,
            FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
        )
    ",
    
    'system_updates' => "
        CREATE TABLE IF NOT EXISTS system_updates (
            update_id INTEGER PRIMARY KEY AUTOINCREMENT,
            version TEXT NOT NULL,
            update_type TEXT NOT NULL,
            description TEXT NOT NULL,
            changelog TEXT NULL,
            is_mandatory INTEGER NOT NULL DEFAULT 0,
            min_version_required TEXT NULL,
            release_date TEXT NOT NULL,
            deployment_status TEXT NOT NULL DEFAULT 'planned',
            created_by INTEGER NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
            UNIQUE(version)
        )
    ",
    
    'update_deployments' => "
        CREATE TABLE IF NOT EXISTS update_deployments (
            deployment_id INTEGER PRIMARY KEY AUTOINCREMENT,
            update_id INTEGER NOT NULL,
            company_id INTEGER NOT NULL,
            deployment_status TEXT NOT NULL DEFAULT 'pending',
            started_at TEXT NULL,
            completed_at TEXT NULL,
            error_message TEXT NULL,
            rollback_reason TEXT NULL,
            deployed_by INTEGER NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (update_id) REFERENCES system_updates(update_id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
            FOREIGN KEY (deployed_by) REFERENCES users(user_id) ON DELETE SET NULL
        )
    "
];

foreach ($saas_tables as $table_name => $create_sql) {
    try {
        // Check if table already exists
        $stmt = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table_name'");
        $exists = $stmt && $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            echo "✓ Table '$table_name' already exists.\n";
        } else {
            echo "Creating table '$table_name'... ";
            $connection->exec($create_sql);
            echo "✓\n";
            $tables_created++;
        }
    } catch (Exception $e) {
        echo "✗ Error creating table '$table_name': " . $e->getMessage() . "\n";
        $errors++;
    }
}

// Create indexes for better query performance
$indexes = [
    'CREATE INDEX IF NOT EXISTS idx_system_activated ON system_control(system_activated)',
    'CREATE INDEX IF NOT EXISTS idx_subscription_status ON system_control(subscription_status)',
    'CREATE INDEX IF NOT EXISTS idx_system_locked ON system_control(system_locked)',
    'CREATE INDEX IF NOT EXISTS idx_payment_company ON subscription_payments(company_id)',
    'CREATE INDEX IF NOT EXISTS idx_payment_date ON subscription_payments(payment_date)',
    'CREATE INDEX IF NOT EXISTS idx_payment_status ON subscription_payments(payment_status)',
    'CREATE INDEX IF NOT EXISTS idx_analytics_company ON system_analytics(company_id)',
    'CREATE INDEX IF NOT EXISTS idx_analytics_metric ON system_analytics(metric_type)',
    'CREATE INDEX IF NOT EXISTS idx_analytics_recorded ON system_analytics(recorded_at)',
    'CREATE INDEX IF NOT EXISTS idx_update_type ON system_updates(update_type)',
    'CREATE INDEX IF NOT EXISTS idx_update_release ON system_updates(release_date)',
    'CREATE INDEX IF NOT EXISTS idx_deployment_update ON update_deployments(update_id)',
    'CREATE INDEX IF NOT EXISTS idx_deployment_company ON update_deployments(company_id)',
    'CREATE INDEX IF NOT EXISTS idx_deployment_status ON update_deployments(deployment_status)'
];

echo "\nCreating indexes...\n";
foreach ($indexes as $idx_sql) {
    try {
        $connection->exec($idx_sql);
    } catch (Exception $e) {
        // Index might already exist, ignore
    }
}

echo "\n✅ SaaS table migration complete!\n";
echo "Tables created: $tables_created\n";
echo "Errors: $errors\n";

echo "\nVerifying tables...\n";
$result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('system_control', 'subscription_payments', 'system_analytics', 'system_updates', 'update_deployments')");
$verified_tables = $result->fetchAll(PDO::FETCH_COLUMN);
echo "Verified tables: " . implode(', ', $verified_tables) . "\n";

if (count($verified_tables) === 5) {
    echo "\n🎉 All SaaS tables successfully created!\n";
    exit(0);
} else {
    echo "\n⚠️ Some tables may be missing. Please check the output above.\n";
    exit(1);
}
?>
