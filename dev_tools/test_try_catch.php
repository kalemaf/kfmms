<?php
session_start();
$_SESSION['tenant_id'] = 1;

try {
    require 'config.inc.php';
    echo "Step 1: Config OK\n";
} catch (Throwable $e) {
    echo "ERROR 1: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    require 'libraries/performance_schema.php';
    echo "Step 2: Schema OK\n";
} catch (Throwable $e) {
    echo "ERROR 2: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done\n";
?>
