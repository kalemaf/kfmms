<?php
// Debug the filter pattern
include 'config.inc.php';
include 'common.inc.php';

echo "<h1>Debug Filter Pattern</h1>";

$_SESSION['tenant_id'] = 11;

$query = 'SELECT * FROM equipment ORDER BY description';
echo "Query: $query<br>";

$filtered = apply_tenant_filter($query);
echo "Filtered: $filtered<br>";

if ($filtered === $query) {
    echo "⚠ Filter NOT applied!<br>";
} else {
    echo "✓ Filter applied<br>";
}