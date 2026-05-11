<?php
include 'config.inc.php';
include 'common.inc.php';

echo "Testing database connection...<br>";

// Check companies
try {
    $companies = $connection->query("SELECT id, company_name FROM companies WHERE id > 3 ORDER BY id");
    echo "Companies query successful<br>";
    $count = 0;
    while ($row = $companies->fetch(PDO::FETCH_ASSOC)) {
        echo "Company: " . $row['id'] . " - " . $row['company_name'] . "<br>";
        $count++;
    }
    echo "Total companies > 3: $count<br>";
} catch (Exception $e) {
    echo "Companies query error: " . $e->getMessage() . "<br>";
}

// Check parts_master tenant distribution
try {
    $parts = $connection->query("SELECT tenant_id, COUNT(*) as cnt FROM parts_master GROUP BY tenant_id ORDER BY tenant_id");
    echo "<br>Parts by tenant:<br>";
    while ($row = $parts->fetch(PDO::FETCH_ASSOC)) {
        echo "Tenant " . $row['tenant_id'] . ": " . $row['cnt'] . " parts<br>";
    }
} catch (Exception $e) {
    echo "Parts query error: " . $e->getMessage() . "<br>";
}
?>