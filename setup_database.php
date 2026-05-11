<?php
/**
 * Database Setup Script for CMMS
 * Creates database, imports schemas, and adds sample users
 */

// Database settings (override config.inc.php for setup)
$hostName = '127.0.0.1';
$userName = 'root';
$password = 'Kalemaf123@@';
$databaseName = 'maintenix';

// Connect to MySQL (without specifying database)
$conn = new mysqli($hostName, $userName, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Connected to MySQL successfully.\n";

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database '$databaseName' created or already exists.\n";
} else {
    die("Error creating database: " . $conn->error . "\n");
}

// Select the database
$conn->select_db($databaseName);

// Import database.sql
echo "Importing database.sql...\n";
$sql = file_get_contents('database.sql');
// Split by semicolon and filter valid SQL statements
$statements = array_filter(array_map('trim', explode(';', $sql)));
$validStatements = [];
foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement) && 
        !preg_match('/^--/', $statement) && 
        !preg_match('/^#/', $statement) && 
        !preg_match('/^Database/', $statement) && 
        !preg_match('/^phpMyAdmin/', $statement) && 
        !preg_match('/^version/', $statement) && 
        !preg_match('/^http/', $statement) && 
        !preg_match('/^Host:/', $statement) && 
        !preg_match('/^Generation/', $statement) && 
        !preg_match('/^Server/', $statement) && 
        !preg_match('/^PHP/', $statement) && 
        !preg_match('/^Database/', $statement) && 
        !preg_match('/^--/', $statement) && 
        (preg_match('/^CREATE/i', $statement) || preg_match('/^INSERT/i', $statement) || preg_match('/^TYPE=/i', $statement))) {
        $validStatements[] = $statement;
    }
}
foreach ($validStatements as $statement) {
    if ($conn->query($statement) === FALSE) {
        echo "Error executing: " . substr($statement, 0, 100) . "...\nError: " . $conn->error . "\n";
    }
}

// Import security_schema.sql
echo "Importing security_schema.sql...\n";
$sql = file_get_contents('security_schema.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));
foreach ($statements as $statement) {
    if (!empty($statement) && !preg_match('/^--/', $statement)) {
        if ($conn->query($statement) === FALSE) {
            echo "Error executing: $statement\nError: " . $conn->error . "\n";
        }
    }
}

// Add sample users (previous users)
echo "Adding sample users...\n";
$users = [
    ['username' => 'admin', 'email' => 'admin@example.com', 'password' => 'admin123', 'role' => 'admin'],
    ['username' => 'manager', 'email' => 'manager@example.com', 'password' => 'manager123', 'role' => 'manager'],
    ['username' => 'technician', 'email' => 'tech@example.com', 'password' => 'tech123', 'role' => 'technician'],
];

foreach ($users as $user) {
    $hash = password_hash($user['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO users (username, email, password_hash, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
    $stmt->bind_param('ssss', $user['username'], $user['email'], $hash, $user['role']);
    if ($stmt->execute()) {
        echo "User '{$user['username']}' added or already exists.\n";
    } else {
        echo "Error adding user '{$user['username']}': " . $stmt->error . "\n";
    }
    $stmt->close();
}

$conn->close();
echo "Database setup complete!\n";
echo "You can now login with:\n";
echo "- admin / admin123 (admin role)\n";
echo "- manager / manager123 (manager role)\n";
echo "- technician / tech123 (technician role)\n";
?>