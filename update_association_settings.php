<?php
session_start();
header('Content-Type: application/json');

// Security Check: Only Admins can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "unauthorized", "message" => "Access denied."]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// 1. GET Request: Fetch current setting configurations
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT * FROM association_settings WHERE id = 1");
    if ($row = $result->fetch_assoc()) {
        echo json_encode(["status" => "success", "settings" => $row]);
    } else {
        echo json_encode(["status" => "error", "message" => "Settings record not found."]);
    }
    $conn->close();
    exit();
}

// 2. POST Request: Save updated configuration rules
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $assoc_name = trim($data['association_name'] ?? 'Lintala Edir');
    $monthly_amount = floatval($data['monthly_contribution'] ?? 100.00);
    $punishment_fee = floatval($data['late_punishment_fee'] ?? 50.00);
    $due_day = intval($data['payment_due_day'] ?? 10);
    $currency = trim($data['currency'] ?? 'ETB');

    $stmt = $conn->prepare("UPDATE association_settings SET association_name = ?, monthly_contribution = ?, late_punishment_fee = ?, payment_due_day = ?, currency = ? WHERE id = 1");
    $stmt->bind_param("sddis", $assoc_name, $monthly_amount, $punishment_fee, $due_day, $currency);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Association settings saved successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update association settings."]);
    }

    $stmt->close();
    $conn->close();
    exit();
}
?>