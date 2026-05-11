<?php
require_once 'config.inc.php';
require_once 'app/PasswordManager.php';

echo "===== PASSWORDMANAGER TEST =====\n\n";

echo "Test 1: Generate 5 temporary passwords\n";
for ($i = 1; $i <= 5; $i++) {
    $temp = PasswordManager::generateTemporaryPassword();
    echo "  #" . $i . ": " . $temp . "\n";
}

echo "\nTest 2: Hash and verify password\n";
$test_password = 'Kalemaf123@@';
$hash = PasswordManager::hashPassword($test_password);
echo "  Original: " . $test_password . "\n";
echo "  Hash: " . $hash . "\n";
echo "  Verify: " . (password_verify($test_password, $hash) ? 'PASS' : 'FAIL') . "\n";

echo "\nTest 3: Verify joi user's actual password\n";
require_once 'config.inc.php';
$stmt = $connection->prepare("SELECT password_hash, temporary_password FROM users WHERE email = 'joi@gmail.com'");
$stmt->execute();
if ($db_type === 'sqlite') {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
}

if ($row) {
    echo "  Password Hash: " . substr($row['password_hash'], 0, 50) . "...\n";
    echo "  Temporary Password: " . $row['temporary_password'] . "\n";
    echo "  Verify temp pwd: " . (password_verify($row['temporary_password'], $row['password_hash']) ? 'PASS' : 'FAIL') . "\n";
    echo "  Verify 'Kalemaf123@@': " . (password_verify('Kalemaf123@@', $row['password_hash']) ? 'PASS' : 'FAIL') . "\n";
}
?>