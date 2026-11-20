<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['finance', 'admin'])) {
    echo json_encode(["status" => "unauthorized", "message" => "Access denied."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$record_id = intval($data['id'] ?? 0);
$type = $data['type'] ?? '';

if (!$record_id || empty($type)) {
    echo json_encode(["status" => "error", "message" => "Invalid parameters."]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Connection failed"]);
    exit();
}

$current_time = date('Y-m-d H:i:s');

if ($type === 'Contribution') {
    $stmt = $conn->prepare("UPDATE contributions SET status = 'Paid', payment_date = ? WHERE id = ?");
    $stmt->bind_param("si", $current_time, $record_id);
} else {
    $stmt = $conn->prepare("UPDATE punishments SET status = 'Paid', punishment_date = ? WHERE id = ?");
    $stmt->bind_param("si", $current_time, $record_id);
}

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Payment verified and approved successfully!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update payment status."]);
}

$stmt->close();
$conn->close();
?>