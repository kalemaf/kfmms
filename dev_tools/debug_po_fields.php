<?php
require_once __DIR__ . '/config.inc.php';

$sql = file_get_contents(__DIR__ . '/migrations/007_po_professional_fields.sql');

$lines = [];
$current = '';
foreach (explode("\n", $sql) as $line) {
    $current .= $line . "\n";
    if (preg_match('/;\\s*$/', $line)) {
        $lines[] = trim($current);
        $current = '';
    }
}
if ($current) {
    $lines[] = trim($current);
}

echo "Found " . count($lines) . " statements\n";

foreach ($lines as $idx => $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt) || $stmt[0] === '-') continue;
    
    echo "\n--- Statement " . ($idx + 1) . " ---\n";
    echo substr($stmt, 0, 80) . "...\n";
    
    try {
        $connection->exec($stmt);
        echo "✓ OK\n";
    } catch (PDOException $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
        // Don't fail, continue
    }
}
?>
