<?php
require_once 'config.inc.php';
require_once 'common.inc.php';

echo "<h2>Equipment Debug Info</h2>";
echo "Session tenant_id: " . ($_SESSION['tenant_id'] ?? 'NOT SET') . "<br>";

echo "<h3>All Equipment in Database:</h3>";
try {
    $all = $connection->query("SELECT id, description, tenant_id FROM equipment ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo "Total: " . count($all) . " records<br>";
    if (count($all) > 0) {
        echo "<ul>";
        foreach ($all as $row) {
            echo "<li>ID {$row['id']}: {$row['description']} (tenant {$row['tenant_id']})</li>";
        }
        echo "</ul>";
    } else {
        echo "NO EQUIPMENT IN DATABASE<br>";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "<h3>Equipment visible to tenant " . ($_SESSION['tenant_id'] ?? 1) . ":</h3>";
try {
    $tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    $query = "SELECT * FROM equipment WHERE tenant_id=" . $tenant_id . " ORDER BY description";
    echo "Query: " . htmlspecialchars($query) . "<br>";
    
    $visible = safe_query_all($query);
    echo "Result count: " . count($visible) . "<br>";
    
    if (count($visible) > 0) {
        echo "<ul>";
        foreach ($visible as $row) {
            echo "<li>ID {$row['id']}: {$row['description']}</li>";
        }
        echo "</ul>";
    } else {
        echo "NO EQUIPMENT VISIBLE TO THIS TENANT<br>";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "<h3>Test Direct Query:</h3>";
try {
    $stmt = $connection->prepare("SELECT id, description, tenant_id FROM equipment WHERE tenant_id = ?");
    $stmt->execute([1]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Direct query (tenant=1) returned: " . count($results) . " records<br>";
    if (count($results) > 0) {
        foreach ($results as $row) {
            echo "ID {$row['id']}: {$row['description']}<br>";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
