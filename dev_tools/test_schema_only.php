<?php
echo "Starting...\n";

try {
    echo "About to include performance_schema...\n";
    require 'libraries/performance_schema.php';
    echo "Performance schema included successfully\n";
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
} catch (Throwable $e) {
    echo "Error caught: " . $e->getMessage() . "\n";
}

echo "Done\n";
?>
