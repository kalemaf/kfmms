<?php
require_once __DIR__ . '/config.inc.php';
$log = [];
$log[] = 'db_type=' . $db_type;
$log[] = 'db_file=' . $db_file;
$tables = ['warehouses','warehouse_locations','stock_locales','goods_receipts','goods_receipt_items'];
foreach ($tables as $table) {
    $log[] = "\nTABLE {$table}";
    $stmt = $connection->query("SELECT COUNT(*) as cnt FROM {$table}");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $log[] = "COUNT=" . ($row['cnt'] ?? '');
    }
    $log[] = "PRAGMA foreign_key_list({$table})";
    $fk = $connection->query("PRAGMA foreign_key_list({$table})");
    if ($fk) {
        while ($r = $fk->fetch(PDO::FETCH_ASSOC)) {
            $log[] = json_encode($r);
        }
    }
}
$log[] = '\nSCHEMA DUMP';
$sql = $connection->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name IN ('warehouses','warehouse_locations','stock_locales','goods_receipts','goods_receipt_items') ORDER BY name");
if ($sql) {
    while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
        $log[] = $row['name'] . ': ' . $row['sql'];
    }
}
file_put_contents(__DIR__ . '/dump_sqlite_fk.txt', implode("\n", $log));
