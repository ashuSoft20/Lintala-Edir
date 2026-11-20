<?php
session_start();
header('Content-Type: application/json');

$user_role = strtolower($_SESSION['role'] ?? '');
if (!isset($_SESSION['username']) || !in_array($user_role, ['finance', 'admin'])) {
    echo json_encode(["status" => "unauthorized", "message" => "Access denied."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$transaction_id = intval($data['id'] ?? 0);
$type = $data['type'] ?? '';

if ($transaction_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid transaction ID."]);
    exit();
}

require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$current_date = date('Y-m-d H:i:s');

// 1. Fetch transaction record
$stmt = $conn->prepare("SELECT contribution_id, username FROM transactions WHERE id = ?");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    echo json_encode(["status" => "error", "message" => "Transaction record not found."]);
    exit();
}

$contribution_id = $res['contribution_id'];
$username = $res['username'];

// 2. Update transaction status to Paid
$up_tx = $conn->prepare("UPDATE transactions SET status = 'Paid' WHERE id = ?");
$up_tx->bind_param("i", $transaction_id);
$up_tx->execute();
$up_tx->close();

// 3. Update contributions table to Paid
if ($contribution_id && $contribution_id > 0) {
    $up_cb = $conn->prepare("UPDATE contributions SET status = 'Paid', payment_date = ? WHERE id = ?");
    $up_cb->bind_param("si", $current_date, $contribution_id);
    $up_cb->execute();
    $up_cb->close();
} else {
    // Fallback: update active contribution for this user if contribution_id wasn't directly linked
    $up_cb = $conn->prepare("UPDATE contributions SET status = 'Paid', payment_date = ? WHERE username = ? AND is_active = 1 AND status != 'Paid'");
    $up_cb->bind_param("ss", $current_date, $username);
    $up_cb->execute();
    $up_cb->close();
}

echo json_encode(["status" => "success", "message" => "Payment successfully verified and updated to Paid!"]);

$conn->close();
?>