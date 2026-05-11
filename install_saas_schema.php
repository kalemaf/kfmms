<?php
/**
 * Install SaaS Control Schema
 * Run this script to set up the SaaS control database tables
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

echo "🔧 Installing SaaS Control Schema...\n";

if (!$db_available) {
    die("❌ Database connection failed\n");
}

$sql = file_get_contents('saas_control_schema.sql');
if (!$sql) {
    die("❌ Could not read saas_control_schema.sql\n");
}

echo "Read " . strlen($sql) . " characters from schema file\n";

// Remove SQL comments (lines starting with --)
$lines = explode("\n", $sql);
$clean_lines = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (!empty($trimmed) && !preg_match('/^--/', $trimmed)) {
        $clean_lines[] = $line;
    }
}
$sql = implode("\n", $clean_lines);

// Split into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));
echo "Found " . count($statements) . " statements after cleaning and splitting\n";

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (empty($statement)) continue;

    echo "Processing statement: " . substr($statement, 0, 50) . "...\n";

    try {
        if ($db_type === 'mysql') {
            if ($connection->query($statement)) {
                $success_count++;
                echo "✅ Statement executed successfully\n";
            } else {
                $error_count++;
                echo "❌ Error: " . $connection->error . "\n";
                echo "   Statement: " . substr($statement, 0, 100) . "...\n";
            }
        } elseif ($db_type === 'sqlite') {
            $connection->exec($statement);
            $success_count++;
            echo "✅ Statement executed successfully\n";
        }
    } catch (Exception $e) {
        $error_count++;
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo "   Statement: " . substr($statement, 0, 100) . "...\n";
    }
}

echo "\n📊 Installation Summary:\n";
echo "   ✅ Successful: $success_count\n";
echo "   ❌ Errors: $error_count\n";

if ($error_count == 0) {
    echo "\n🎉 SaaS Control Schema installed successfully!\n";
    echo "   You can now use the SaaS Control Center at admin_roles.php\n";
} else {
    echo "\n⚠️  Some errors occurred. Please check the output above.\n";
}

$connection->close();
?>