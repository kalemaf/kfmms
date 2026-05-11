<?php
// Render print_wo.php for a specific WO id and write to file
$wo_id_local = $argv[1] ?? 0;
if (!$wo_id_local) { echo "Usage: php render_print.php <wo_id>\n"; exit(1); }
$root = dirname(__DIR__);
include($root . '/config.inc.php');
$_REQUEST['wo_id'] = (int)$wo_id_local;
ob_start();
include($root . '/print_wo.php');
$html = ob_get_clean();
$out = __DIR__ . "/wo_" . $wo_id_local . "_print.html";
file_put_contents($out, $html);
echo "Wrote print HTML to $out\n";
?>