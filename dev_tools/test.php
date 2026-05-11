<?php
require_once 'config.inc.php';
require_once 'common.inc.php';
require_debug_page_access();

echo "PHP is working!";
echo "<br>Current time: " . date('Y-m-d H:i:s');
echo "<br>Config loaded: " . (file_exists('config.inc.php') ? 'Yes' : 'No');
?>
