<?php
if (!file_exists('config.inc.php')) {
    echo "config.inc.php not found\n";
    exit(1);
}

require_once 'config.inc.php';
echo "Config loaded\n";

if (!file_exists('common.inc.php')) {
    echo "common.inc.php not found\n";
    exit(1);
}

echo "About to load common.inc.php\n";
require_once 'common.inc.php';
echo "Common loaded\n";

echo "get_current_tenant_id(): " . get_current_tenant_id() . "\n";
?>
