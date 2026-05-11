<?php
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/common.inc.php';

echo "Attempting to add password_change_required column...\n";

try {
    // Check if column already exists
    $pragmaStmt = $connection->prepare("PRAGMA table_info(users)");
    $pragmaStmt->execute();
    $result = $pragmaStmt->get_result();
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['name'];
    }
    $pragmaStmt->close();
    
    if (in_array('password_change_required', $columns)) {
        echo "✓ Column already exists, skipping.\n";
    } else {
        $sql = 'ALTER TABLE users ADD COLUMN password_change_required INTEGER DEFAULT 0';
        $connection->exec($sql);
        echo "✓ Column added successfully!\n";
    }
    
    // Verify
    $pragmaStmt = $connection->prepare("PRAGMA table_info(users)");
    $pragmaStmt->execute();
    $result = $pragmaStmt->get_result();
    $found = false;
    while ($row = $result->fetch_assoc()) {
        if ($row['name'] === 'password_change_required') {
            echo "✓ Verified: password_change_required column exists (Type: " . $row['type'] . ")\n";
            $found = true;
        }
    }
    $pragmaStmt->close();
    
    if (!$found) {
        echo "✗ Column still not found after adding!\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
