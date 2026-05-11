<?php
echo "Starting...\n";

try {
    echo "About to include performanceService...\n";
    require 'libraries/performanceService.php';
    echo "Performance service included successfully\n";
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
} catch (Throwable $e) {
    echo "Error caught: " . $e->getMessage() . "\n";
}

echo "Done\n";
?>
