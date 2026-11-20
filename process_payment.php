<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "unauthorized", "message" => "Session expired"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$gateway = $data['gateway'] ?? '';
$phone = $data['phone_number'] ?? '';
$record_id = intval($data['record_id'] ?? 0);
$payment_type = $data['payment_type'] ?? 'contribution';

if (!$record_id || empty($gateway)) {
    echo json_encode(["status" => "error", "message" => "Invalid payment payload"]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$username = $_SESSION['username'];
$current_time = date('Y-m-d H:i:s');

// Prepare statement based on whether it's a punishment or contribution fee
if ($payment_type === 'punishment') {
    $stmt = $conn->prepare("UPDATE punishments SET status = 'Paid' WHERE id = ? AND username = ?");
    $stmt->bind_param("is", $record_id, $username);
} else {
    $stmt = $conn->prepare("UPDATE contributions SET status = 'Paid', payment_date = ? WHERE id = ? AND username = ?");
    $stmt->bind_param("sis", $current_time, $record_id, $username);
}

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode([
        "status" => "success", 
        "message" => "Payment completed successfully via " . ucfirst($gateway)
    ]);
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "Could not update transaction record. Ensure the record belongs to your account."
    ]);
}

$stmt->close();
$conn->close();
?>