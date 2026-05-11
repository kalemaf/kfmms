<?php
// Convert MySQL-escaped strings to SQLite format
require_once __DIR__ . '/config.inc.php';

$sql = file_get_contents(__DIR__ . '/migrations/006_restore_work_orders.sql');

// In MySQL dumps, \' should be replaced with '' for SQLite
// But we need to be careful with actual backslashes
$sql = str_replace("\\'", "''", $sql);
$sql = str_replace('\\r\\n', "\n", $sql);
$sql = str_replace('\\n', "\n", $sql);
$sql = str_replace('\\t', "\t", $sql);

echo "Testing converted SQL...\n";

try {
    $connection->exec($sql);
    echo "Successfully executed!\n";
    
    // Check how many work orders were inserted
    $result = $connection->query('SELECT COUNT(*) FROM work_orders WHERE wo_id IN (6, 8, 9, 13)');
    $count = $result->fetch(PDO::FETCH_COLUMN);
    echo "Inserted $count work orders\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
