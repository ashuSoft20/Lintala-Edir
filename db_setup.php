<?php
$host = "localhost";
$db_user = "root";
$db_pass = ""; 
$db_name = "lintalaedir";

// Connect to MySQL server
$conn = new mysqli($host, $db_user, $db_pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS $db_name");
$conn->select_db($db_name);

// Create users table
$tableQuery = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL
)";
$conn->query($tableQuery);

// Insert test users with secure hashes (Passwords are role + '123', e.g., admin123)
$users = [
    ['admin_user', password_hash('admin123', PASSWORD_DEFAULT), 'admin'],
    ['finance_user', password_hash('finance123', PASSWORD_DEFAULT), 'finance'],
    ['sec_user', password_hash('sec123', PASSWORD_DEFAULT), 'seceratery'],
    ['member_user', password_hash('member123', PASSWORD_DEFAULT), 'members']
];

$stmt = $conn->prepare("INSERT IGNORE INTO users (username, password, role) VALUES (?, ?, ?)");
foreach ($users as $u) {
    $stmt->bind_param("sss", $u[0], $u[1], $u[2]);
    $stmt->execute();
}
$stmt->close();
$conn->close();

echo "<h3 style='color: green;'>Database and tables configured successfully!</h3>";
echo "<p>You can now test logging in with these accounts:</p>";
echo "<ul>";
echo "<li><strong>Admin:</strong> admin_user / admin123</li>";
echo "<li><strong>Finance:</strong> finance_user / finance123</li>";
echo "<li><strong>Secretary:</strong> sec_user / sec123</li>";
echo "<li><strong>Member:</strong> member_user / member123</li>";
echo "</ul>";
echo "<p><a href='Login.html'>Go to Login Page</a></p>";
?>