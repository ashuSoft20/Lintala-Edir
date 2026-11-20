<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "unauthorized", "message" => "Please log in first."]);
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// 1. Get User Details
$user_stmt = $conn->prepare("SELECT full_name, username FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$user_res = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// 2. Get Active Contributions for this user (where is_active = 1)
$contrib_stmt = $conn->prepare("SELECT id, month_name, contribution_year, amount, deadline, status, is_active FROM contributions WHERE username = ? AND is_active = 1");
$contrib_stmt->bind_param("s", $username);
$contrib_stmt->execute();
$contrib_res = $contrib_stmt->get_result();
$active_contributions = [];
while ($row = $contrib_res->fetch_assoc()) {
    $active_contributions[] = $row;
}
$contrib_stmt->close();

// 3. Get Unpaid Punishments / Fines for this user
$punish_stmt = $conn->prepare("SELECT id, reason, amount, status FROM punishments WHERE username = ? AND status IN ('UnPaid', 'Pending')");
$punish_stmt->bind_param("s", $username);
$punish_stmt->execute();
$punish_res = $punish_stmt->get_result();
$active_punishments = [];
while ($row = $punish_res->fetch_assoc()) {
    $active_punishments[] = $row;
}
$punish_stmt->close();

echo json_encode([
    "status" => "success",
    "full_name" => $user_res['full_name'] ?? $username,
    "active_contributions" => $active_contributions,
    "active_punishments" => $active_punishments
]);

$conn->close();
?>