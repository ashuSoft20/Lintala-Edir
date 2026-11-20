<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in via username session
if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "unauthorized", "message" => "No active session."]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]);
    exit();
}

$username = $_SESSION['username'];

// Fetch punishments using the 'username' column as shown in phpMyAdmin
$stmt = $conn->prepare("SELECT id, reason, punishment_date, amount, status FROM punishments WHERE username = ? ORDER BY id DESC");
$stmt->bind_param("s", $username);

if ($stmt->execute()) {
    $result = $stmt->get_result();

    $punishments = [];
    $total_unpaid = 0;

    while ($row = $result->fetch_assoc()) {
        $punishments[] = $row;
        if ($row['status'] === 'UnPaid') {
            $total_unpaid += floatval($row['amount']);
        }
    }

    echo json_encode([
        "status" => "success",
        "full_name" => $_SESSION['full_name'] ?? $username,
        "username" => $username,
        "total_unpaid" => $total_unpaid,
        "punishments" => $punishments
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Query execution failed."]);
}

$stmt->close();
$conn->close();
?>