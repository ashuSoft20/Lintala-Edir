<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$id = intval($_GET['id'] ?? 0);
$username = $_SESSION['username'];

require_once __DIR__ . '/DBConction.php';
$stmt = $conn->prepare("SELECT id, month_name, contribution_year, amount, punishment_price, status FROM contributions WHERE id = ? AND username = ?");
$stmt->bind_param("is", $id, $username);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if ($result) {
    echo json_encode(["status" => "success", "full_name" => $_SESSION['full_name'], "contribution" => $result]);
} else {
    echo json_encode(["status" => "error", "message" => "Contribution record not found."]);
}
?>