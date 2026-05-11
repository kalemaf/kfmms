<?php
// Read the SQL file and see the actual bytes
$sql = file_get_contents(__DIR__ . '/migrations/006_restore_work_orders.sql');

// Find the line with WO_SEARCH problem
$lines = explode("\n", $sql);
foreach ($lines as $idx => $line) {
    if (stripos($line, 'WO_SEARCH') !== false) {
        echo "Line with WO_SEARCH issue:\n";
        echo "Raw: " . var_export($line, true) . "\n\n";
        echo "Length: " . strlen($line) . "\n";
        echo "Hex: " . bin2hex(substr($line, -100)) . "\n";
        
        // Try replacing \r\n with actual newlines
        $fixed = str_replace('\\r\\n', ' ', $line);
        echo "\nAfter replacing \\r\\n with space:\n";
        echo $fixed . "\n";
    }
}
?>
