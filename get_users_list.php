<?php
session_start();
header('Content-Type: application/json');

// Security Check: Admin Access Only
$user_role = strtolower($_SESSION['role'] ?? '');
if (!isset($_SESSION['username']) || $user_role !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'list') {
    $result = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY id DESC");
    $users = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    echo json_encode(["status" => "success", "users" => $users]);

} elseif ($action === 'add') {
    $username  = trim($input['username'] ?? '');
    $full_name = trim($input['full_name'] ?? '');
    $role      = trim($input['role'] ?? 'members');
    $password  = trim($input['password'] ?? '');

    if (empty($username) || empty($full_name) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Username, Full Name, and Password are all required."]);
        exit();
    }

    // Check if username already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Username already exists."]);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();

    // SECURE HASHING FOR NEW USER
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, full_name, role, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $full_name, $role, $hashed_password);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "User registered with secure hashed password!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to add user."]);
    }
    $stmt->close();

} elseif ($action === 'update_user') {
    $id        = intval($input['id'] ?? 0);
    $new_user  = trim($input['username'] ?? '');
    $full_name = trim($input['full_name'] ?? '');
    $role      = trim($input['role'] ?? '');
    $password  = trim($input['password'] ?? '');

    if ($id <= 0 || empty($new_user) || empty($full_name) || empty($role)) {
        echo json_encode(["status" => "error", "message" => "ID, Username, Full Name, and Role are required."]);
        exit();
    }

    // 1. Fetch the original username from the database before updating
    $orig_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $orig_stmt->bind_param("i", $id);
    $orig_stmt->execute();
    $orig_res = $orig_stmt->get_result();
    if ($orig_row = $orig_res->fetch_assoc()) {
        $original_username = $orig_row['username'];
    } else {
        echo json_encode(["status" => "error", "message" => "User account not found."]);
        exit();
    }
    $orig_stmt->close();

    // 2. Start a Database Transaction to ensure safe multi-table updates
    $conn->begin_transaction();

    try {
        // Update the `users` table
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt1 = $conn->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, password = ? WHERE id = ?");
            $stmt1->bind_param("ssssi", $new_user, $full_name, $role, $hashed_password, $id);
        } else {
            $stmt1 = $conn->prepare("UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?");
            $stmt1->bind_param("sssi", $new_user, $full_name, $role, $id);
        }
        $stmt1->execute();
        $stmt1->close();

        // 3. Cascade username change to `contributions` and `transactions` tables if the username changed
        if ($original_username !== $new_user) {
            $stmt2 = $conn->prepare("UPDATE contributions SET username = ? WHERE username = ?");
            $stmt2->bind_param("ss", $new_user, $original_username);
            $stmt2->execute();
            $stmt2->close();

            $stmt3 = $conn->prepare("UPDATE transactions SET username = ? WHERE username = ?");
            if ($stmt3) {
                $stmt3->bind_param("ss", $new_user, $original_username);
                $stmt3->execute();
                $stmt3->close();
            }
        }

        // Commit all changes across tables
        $conn->commit();
        echo json_encode(["status" => "success", "message" => "User account and all related records updated successfully!"]);

    } catch (Exception $e) {
        // Rollback changes if anything fails
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Failed to update user records: " . $e->getMessage()]);
    }

} elseif ($action === 'delete') {
    $username = trim($input['username'] ?? '');

    if (empty($username)) {
        echo json_encode(["status" => "error", "message" => "Invalid username for deletion."]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "User deleted successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete user."]);
    }
    $stmt->close();

} else {
    echo json_encode(["status" => "error", "message" => "Invalid action requested."]);
}

$conn->close();
?>