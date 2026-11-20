<?php
session_start();
header('Content-Type: application/json');

$user_role = strtolower($_SESSION['role'] ?? '');
if (!isset($_SESSION['username']) || !in_array($user_role, ['finance', 'admin'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$record_id = intval($input['id'] ?? 0);
$new_status = trim($input['status'] ?? '');

if ($record_id <= 0 || !in_array($new_status, ['Paid', 'XXX'])) {
    echo json_encode(["status" => "error", "message" => "Invalid parameters provided."]);
    exit();
}

if ($new_status === 'Paid') {
    $stmt = $conn->prepare("UPDATE contributions SET status = 'Paid', payment_date = NOW() WHERE id = ?");
} else {
    $stmt = $conn->prepare("UPDATE contributions SET status = 'XXX', payment_date = NULL WHERE id = ?");
}

$stmt->bind_param("i", $record_id);
if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Record updated successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Database update failed."]);
}

$stmt->close();
$conn->close();
?>