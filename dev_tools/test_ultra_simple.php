<?php
// Ultra-simple test - just require the file
error_log("TEST_START");
echo "START\n";

require 'config.inc.php';
echo "CONFIG LOADED\n";

require 'common.inc.php';
echo "COMMON LOADED\n";

ob_start();
try {
    require 'libraries/predictive_maintenance.php';
    echo "LOADED\n";
} catch (Throwable $t) {
    echo "CAUGHT: " . $t->getMessage() . "\n";
}
$output = ob_get_clean();

echo "OUTPUT: " . strlen($output) . " bytes\n";
if ($output) {
    echo substr($output, 0, 500) . "\n";
}

?>
