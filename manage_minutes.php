
<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'seceratery') {
    echo json_encode(["status" => "unauthorized", "message" => "Access denied."]);
    exit();
}

require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    $res = $conn->query("SELECT * FROM meeting_minutes ORDER BY meeting_date DESC");
    $list = [];

    while ($r = $res->fetch_assoc()) {
        $list[] = $r;
    }

    echo json_encode(["status" => "success", "minutes" => $list]);
    exit();
}

if ($action === 'add') {
    $data = json_decode(file_get_contents("php://input"), true);

    $date = $data['meeting_date'] ?? '';
    $agenda = trim($data['agenda'] ?? '');
    $minutes = trim($data['minutes'] ?? '');

    if (empty($date) || empty($agenda) || empty($minutes)) {
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit();
    }

    $stmt = $conn->prepare(
        "INSERT INTO meeting_minutes (meeting_date, agenda, minutes) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("sss", $date, $agenda, $minutes);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Meeting minutes recorded successfully!"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to save meeting minutes."
        ]);
    }

    $stmt->close();
    exit();
}

/* Delete a meeting record */
if ($action === 'delete') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid meeting record ID."
        ]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM meeting_minutes WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "Meeting record deleted successfully!"
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Meeting record not found."
            ]);
        }
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to delete meeting record."
        ]);
    }

    $stmt->close();
    exit();
}

$conn->close();

echo json_encode([
    "status" => "error",
    "message" => "Invalid action."
]);
?>
