<?php
/**
 * Run License Schema
 */

require_once 'config.inc.php';

if (isset($connection) && is_object($connection)) {
    echo 'Running license schema...' . PHP_EOL;

    $schema = file_get_contents('license_schema.sql');
    if ($schema) {
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        $success_count = 0;
        $error_count = 0;

        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                echo 'Executing: ' . substr($statement, 0, 60) . '...' . PHP_EOL;
                if ($connection->query($statement) === TRUE) {
                    echo '✓ Success' . PHP_EOL;
                    $success_count++;
                } else {
                    echo '✗ Error: ' . $connection->error . PHP_EOL;
                    $error_count++;
                }
            }
        }

        echo "Schema execution complete. Success: $success_count, Errors: $error_count" . PHP_EOL;
    } else {
        echo 'Could not read license_schema.sql' . PHP_EOL;
    }
} else {
    echo 'Database connection not available.' . PHP_EOL;
}
?>