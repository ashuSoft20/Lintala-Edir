<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (isset($_SESSION['full_name'])) {
    echo json_encode([
        "status" => "success",
        "full_name" => $_SESSION['full_name']
    ]);
} else {
    echo json_encode([
        "status" => "unauthorized"
    ]);
}
?>