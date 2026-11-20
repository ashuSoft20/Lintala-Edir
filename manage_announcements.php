<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'seceratery') {
    echo json_encode(["status" => "unauthorized"]); exit();
}
require_once __DIR__ . '/DBConction.php';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    $res = $conn->query("SELECT * FROM announcements ORDER BY id DESC");
    $list = []; while($r = $res->fetch_assoc()) $list[] = $r;
    echo json_encode(["status" => "success", "announcements" => $list]);
    exit();
}
if ($action === 'add') {
    $data = json_decode(file_get_contents("php://input"), true);
    $title = trim($data['title']); $content = trim($data['content']);
    $stmt = $conn->prepare("INSERT INTO announcements (title, content) VALUES (?, ?)");
    $stmt->bind_param("ss", $title, $content);
    if($stmt->execute()) echo json_encode(["status" => "success", "message" => "Announcement posted!"]);
    else echo json_encode(["status" => "error", "message" => "Failed to post."]);
    exit();
}
//delete announcement
if ($action === 'delete') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = intval($data['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid announcement ID."]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Announcement deleted successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete announcement."]);
    }

    $stmt->close();
    exit();
}
?>