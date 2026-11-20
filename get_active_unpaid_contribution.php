<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$username = $_SESSION['username'];

require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// Get the latest active unpaid contribution record for this logged-in member
$stmt = $conn->prepare("SELECT id, month_name, contribution_year, amount, punishment_price, status 
                        FROM contributions 
                        WHERE username = ? AND status = 'UnPaid' AND is_active = 1 
                        ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

$stmt->close();
$conn->close();

if ($result) {
    echo json_encode([
        "status" => "success", 
        "full_name" => $_SESSION['full_name'] ?? $username, 
        "contribution" => $result
    ]);
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "No pending unpaid contributions found."
    ]);
}
?>