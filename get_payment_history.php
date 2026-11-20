<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized: No active session."]);
    exit();
}

$username = $_SESSION['username'];

require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

// Fetch payment logs directly from the transactions table
$stmt = $conn->prepare("SELECT id, tx_ref, contribution_id, amount, status, created_at FROM transactions WHERE username = ? AND (status = 'Paid' OR status = 'Success') ORDER BY id DESC");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
while ($row = $result->fetch_assoc()) {
    $payments[] = [
        "type" => "Contribution Payment",
        "description" => "Transaction Ref: " . $row['tx_ref'],
        "amount" => $row['amount'],
        "status" => $row['status'],
        "date" => $row['created_at']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    "status" => "success",
    "payments" => $payments
]);
exit();
?>