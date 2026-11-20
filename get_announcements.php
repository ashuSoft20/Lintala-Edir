<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// Query latest meeting or announcement (adjust table name based on your schema, e.g., announcements or meeting_minutes)
$result = $conn->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 1");

if ($result && $result->num_rows > 0) {
    $meeting = $result->fetch_assoc();
    echo json_encode([
        "status" => "success",
        "latest_meeting" => $meeting
    ]);
} else {
    echo json_encode([
        "status" => "empty",
        "latest_meeting" => null
    ]);
}

$conn->close();
?>