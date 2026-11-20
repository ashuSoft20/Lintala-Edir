
<?php
session_start();

// ==========================================
// 2. CHECK REQUEST METHOD
// ==========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: Login.html");
    exit();
}

// ==========================================
// 3. VALIDATE USER INPUT
// ==========================================

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Ensure both inputs are strings
if (!is_string($username) || !is_string($password)) {
    echo "<script>alert('Invalid login request!'); window.location.href='Login.html';</script>";
    exit();
}

// Remove unnecessary spaces from username only
$username = trim($username);

// Do NOT trim the password because spaces may be part of it

if ($username === '' || $password === '') {
    echo "<script>alert('Please enter username and password!'); window.location.href='Login.html';</script>";
    exit();
}

// ==========================================
// 4. CONNECT TO DATABASE
// ==========================================

require_once __DIR__ . '/DBConction.php';

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);

    echo "<script>alert('Unable to connect to the server. Please try again later.'); window.location.href='Login.html';</script>";
    exit();
}

// ==========================================
// 5. PREPARED STATEMENT
// PREVENT SQL INJECTION
// ==========================================

$stmt = $conn->prepare(
    "SELECT id, username, full_name, password, role
     FROM users
     WHERE username = ?"
);

if (!$stmt) {
    error_log("SQL prepare failed: " . $conn->error);

    $conn->close();

    echo "<script>alert('Something went wrong. Please try again.'); window.location.href='Login.html';</script>";
    exit();
}

// Bind username to SQL placeholder
$stmt->bind_param("s", $username);

// Execute SQL query
if (!$stmt->execute()) {
    error_log("SQL execution failed: " . $stmt->error);

    $stmt->close();
    $conn->close();

    echo "<script>alert('Something went wrong. Please try again.'); window.location.href='Login.html';</script>";
    exit();
}

// ==========================================
// 6. FETCH USER INFORMATION
// ==========================================

$stmt->bind_result(
    $id,
    $db_username,
    $full_name,
    $hashed_password,
    $role
);

if ($stmt->fetch()) {

    // ==========================================
    // 7. VERIFY PASSWORD
    // ==========================================

    if (password_verify($password, $hashed_password)) {

        // ==========================================
        // 8. REGENERATE SESSION ID
        // PREVENT SESSION FIXATION
        // ==========================================

        session_regenerate_id(true);

        // ==========================================
        // 9. STORE USER INFORMATION IN SESSION
        // ==========================================

        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $db_username;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['role'] = $role;

        // ==========================================
        // 10. ROLE-BASED REDIRECTION
        // ==========================================

        switch ($role) {

            case 'admin':
                $redirect = "Admin/admin_dashboard.html";
                break;

            case 'finance':
                $redirect = "Finance/finance_dashboard.html";
                break;

            case 'seceratery':
                $redirect = "Secratry/secretary_dashboard.html";
                break;

            case 'members':
            default:
                $redirect = "members/membersDashboard.html";
                break;
        }

        // Close database resources
        $stmt->close();
        $conn->close();

        // Redirect user to appropriate dashboard
        header("Location: " . $redirect);
        exit();

    } else {

        // Incorrect password
        echo "<script>alert('Invalid username or password!'); window.location.href='Login.html';</script>";

    }

} else {

    // Username does not exist
    // Use the same message to avoid revealing registered usernames
    echo "<script>alert('Invalid username or password!'); window.location.href='Login.html';</script>";

}

// ==========================================
// 11. CLOSE DATABASE CONNECTION
// ==========================================

$stmt->close();
$conn->close();

?>
