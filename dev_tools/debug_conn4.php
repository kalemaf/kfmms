<?php
require "config.inc.php";
require "common.inc.php";
echo "Testing SQLite PDO connection:\n";
try {
    echo "Attempting query with PDO::query():\n";
    $stmt = $connection->query("SELECT id, company_name FROM companies LIMIT 1");
    if ($stmt) {
        echo "Query returned: " . get_class($stmt) . "\n";
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        var_dump($row);
    } else {
        echo "Query returned false\n";
    }
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
?>
