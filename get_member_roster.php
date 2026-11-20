<?php
session_start();
header('Content-Type: application/json');

// Security Check: Ensure user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// Fetch member roster mapping phone_number to phone for the frontend
$result = $conn->query("SELECT id, full_name, username, phone_number as phone, created_at, role FROM users ORDER BY id DESC");

$members = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "full_name" => $_SESSION['full_name'] ?? $_SESSION['username'],
    "members" => $members
]);

$conn->close();
?>