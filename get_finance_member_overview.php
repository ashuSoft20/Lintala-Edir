<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access. No active session found."]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$sql = "SELECT 
            u.username,
            COALESCE(u.full_name, u.username) AS full_name,
            COALESCE(c.month_name, 'N/A') AS month_name,
            COALESCE(c.contribution_year, YEAR(CURDATE())) AS contribution_year,
            COALESCE(c.amount, 0.00) AS contribution_amount,
            COALESCE(c.punishment_price, 0.00) AS punishment_price,
            COALESCE(c.is_active, 0) AS is_active
        FROM users u
        LEFT JOIN contributions c ON u.username = c.username AND c.is_active = 1
        WHERE u.role = 'member' OR u.role IS NULL
        ORDER BY u.id ASC";

$result = $conn->query($sql);
$members = [];

while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

echo json_encode([
    "status" => "success",
    "full_name" => $_SESSION['full_name'] ?? $_SESSION['username'],
    "members" => $members
]);

$conn->close();
?>