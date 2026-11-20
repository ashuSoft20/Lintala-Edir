<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "unauthorized", "message" => "Please log in first."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id'] ?? 0); // contribution id
$type = $data['type'] ?? 'Contribution';
$ref_number = trim($data['ref_number'] ?? '');
$username = $_SESSION['username'];

if (empty($ref_number)) {
    echo json_encode(["status" => "error", "message" => "Please enter a valid payment reference number."]);
    exit();
}

require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$current_date = date('Y-m-d H:i:s');

// 1. Insert directly into the transactions table with status 'Paid'
$stmt = $conn->prepare("INSERT INTO transactions (username, contribution_id, tx_ref, amount, status) VALUES (?, ?, ?, (SELECT amount FROM contributions WHERE id = ?), 'Paid')");
// Note: Adjust the amount fetch if your frontend passes the exact amount directly
$stmt->bind_param("sisi", $username, $id, $ref_number, $id);
$stmt->execute();
$stmt->close();

// 2. Automatically update the contributions table status to 'Paid' instantly
$up_cb = $conn->prepare("UPDATE contributions SET status = 'Paid', payment_date = ? WHERE id = ?");
$up_cb->bind_param("si", $current_date, $id);

if ($up_cb->execute()) {
    echo json_encode(["status" => "success", "message" => "Payment submitted and automatically marked as Paid!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update contribution status."]);
}

$up_cb->close();
$conn->close();
?>