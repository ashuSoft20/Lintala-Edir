<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "unauthorized", "message" => "No active session"]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$username = $_SESSION['username'];

// Handle GET request to fetch current profile details
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT username, full_name, role, phone_number, profile_photo FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        "status" => "success",
        "username" => $user['username'],
        "full_name" => $user['full_name'],
        "role" => $user['role'],
        "phone_number" => $user['phone_number'] ?? '',
        "profile_photo" => $user['profile_photo'] ?? ''
    ]);
    $conn->close();
    exit();
}

// Handle POST request to update profile details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone_number     = trim($_POST['phone_number'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate password change if a new password is provided
    $hashed_pwd = null;
    if (!empty($new_password)) {
        if (empty($current_password)) {
            echo json_encode(["status" => "error", "message" => "Please enter your current password to set a new one."]);
            exit();
        }

        if ($new_password !== $confirm_password) {
            echo json_encode(["status" => "error", "message" => "New passwords do not match."]);
            exit();
        }

        // Fetch user's existing hashed password from database to verify current password
        $pwd_stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
        $pwd_stmt->bind_param("s", $username);
        $pwd_stmt->execute();
        $pwd_res = $pwd_stmt->get_result()->fetch_assoc();
        $pwd_stmt->close();

        if (!$pwd_res || !password_verify($current_password, $pwd_res['password'])) {
            echo json_encode(["status" => "error", "message" => "Incorrect current password."]);
            exit();
        }

        // Hash the new password securely
        $hashed_pwd = password_hash($new_password, PASSWORD_BCRYPT);
    }

    // File upload handler
    $photo_path = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $file_name = 'profile_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_path)) {
            $photo_path = $target_path;
        }
    }

    // Dynamic query building based on provided inputs
    if ($hashed_pwd && $photo_path) {
        $stmt = $conn->prepare("UPDATE users SET phone_number = ?, password = ?, profile_photo = ? WHERE username = ?");
        $stmt->bind_param("ssss", $phone_number, $hashed_pwd, $photo_path, $username);
    } elseif ($hashed_pwd) {
        $stmt = $conn->prepare("UPDATE users SET phone_number = ?, password = ? WHERE username = ?");
        $stmt->bind_param("sss", $phone_number, $hashed_pwd, $username);
    } elseif ($photo_path) {
        $stmt = $conn->prepare("UPDATE users SET phone_number = ?, profile_photo = ? WHERE username = ?");
        $stmt->bind_param("sss", $phone_number, $photo_path, $username);
    } else {
        $stmt = $conn->prepare("UPDATE users SET phone_number = ? WHERE username = ?");
        $stmt->bind_param("ss", $phone_number, $username);
    }

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Profile updated successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update profile."]);
    }

    $stmt->close();
    $conn->close();
    exit();
}
?>