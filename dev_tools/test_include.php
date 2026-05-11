<?php
$test = 1;
echo "About to include schema...\n";
@include __DIR__ . '/libraries/performance_schema.php';
if (!function_exists('ensure_sla_policies_table')) {
    echo "Function doesn't exist!\n";
} else {
    echo "Function exists!\n";
}
?>
