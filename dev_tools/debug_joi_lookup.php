<?php
require_once 'config.inc.php';

echo "==== DEBUGGING JOI@GMAIL.COM LOOKUP ====\n\n";

if (!isset($connection)) {
    echo "ERROR: Database not connected\n";
    exit(1);
}

// Method 1: Simple query
echo "Method 1: Simple SELECT query\n";
$result1 = $connection->query("SELECT user_id, email FROM users WHERE email = 'joi@gmail.com'");
if ($result1) {
    echo "  Rows found: " . $result1->num_rows . "\n";
    if ($row = $result1->fetch_assoc()) {
        echo "  ✓ Found: ID=" . $row['user_id'] . ", Email=" . $row['email'] . "\n";
    }
} else {
    echo "  Query error: " . $connection->error . "\n";
}

echo "\nMethod 2: Prepared statement with parameter\n";
$stmt = $connection->prepare("SELECT user_id, email FROM users WHERE email = ?");
if (!$stmt) {
    echo "  Prepare error: " . $connection->error . "\n";
} else {
    $email = 'joi@gmail.com';
    echo "  Binding parameter: '" . $email . "'\n";
    $stmt->bind_param('s', $email);
    echo "  Executing...\n";
    $stmt->execute();
    echo "  Execute result: " . ($stmt->error ? "ERROR: " . $stmt->error : "OK") . "\n";
    
    $result2 = $stmt->get_result();
    echo "  Rows found: " . $result2->num_rows . "\n";
    
    if ($row = $result2->fetch_assoc()) {
        echo "  ✓ Found: ID=" . $row['user_id'] . ", Email=" . $row['email'] . "\n";
    } else {
        echo "  ✗ No rows returned\n";
    }
    $stmt->close();
}

echo "\nMethod 3: Prepared statement with OR\n";
$stmt3 = $connection->prepare("SELECT user_id, email, username FROM users WHERE email = ? OR username = ?");
if (!$stmt3) {
    echo "  Prepare error: " . $connection->error . "\n";
} else {
    $email = 'joi@gmail.com';
    $username = 'joi@gmail.com';
    echo "  Binding parameters: email='" . $email . "', username='" . $username . "'\n";
    $stmt3->bind_param('ss', $email, $username);
    echo "  Executing...\n";
    $stmt3->execute();
    echo "  Execute result: " . ($stmt3->error ? "ERROR: " . $stmt3->error : "OK") . "\n";
    
    $result3 = $stmt3->get_result();
    echo "  Rows found: " . $result3->num_rows . "\n";
    
    if ($row = $result3->fetch_assoc()) {
        echo "  ✓ Found: ID=" . $row['user_id'] . ", Email=" . $row['email'] . ", Username=" . $row['username'] . "\n";
    } else {
        echo "  ✗ No rows returned\n";
    }
    $stmt3->close();
}

echo "\nMethod 4: Check all users with 'joi' or similar\n";
$result4 = $connection->query("SELECT user_id, email, username FROM users");
if ($result4) {
    while ($row = $result4->fetch_assoc()) {
        if (stripos($row['email'], 'joi') !== false || stripos($row['username'], 'joi') !== false) {
            echo "  Found: ID=" . $row['user_id'] . ", Email='" . $row['email'] . "', Username='" . $row['username'] . "'\n";
        }
    }
}

echo "\nMethod 5: Show EXACT characters in joi@gmail.com email\n";
$result5 = $connection->query("SELECT user_id, email, HEX(email) as email_hex FROM users WHERE email LIKE '%joi%'");
if ($result5) {
    while ($row = $result5->fetch_assoc()) {
        echo "  Email: '" . $row['email'] . "'\n";
        echo "  Hex: " . $row['email_hex'] . "\n";
        echo "  Length: " . strlen($row['email']) . "\n\n";
    }
}

?>