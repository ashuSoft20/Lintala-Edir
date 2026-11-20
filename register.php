<?php
session_start();


$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once __DIR__ . '/DBConction.php';

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role     = $_POST['role'];

    if (!empty($username) && !empty($password) && !empty($role)) {
        // 1. Securely hash the password before saving to the database
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 2. Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed_password, $role);

        if ($stmt->execute()) {
            $message = "<p style='color: green;'>User registered successfully! <a href='Login.html'>Login here</a></p>";
        } else {
            // Error code 1062 handles duplicate entry for unique usernames
            if ($conn->errno === 1062) {
                $message = "<p style='color: red;'>Error: Username already exists. Choose a different one.</p>";
            } else {
                $message = "<p style='color: red;'>Error: " . $stmt->error . "</p>";
            }
        }

        $stmt->close();
    } else {
        $message = "<p style='color: red;'>Please fill in all fields.</p>";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register User - LintalaEdir</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .register-box { background: white; padding: 25px; border-radius: 8px; box-shadow: 0px 0px 10px rgba(0,0,0,0.1); width: 320px; }
        .register-box h2 { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn { width: 100%; padding: 10px; background: #28a745; border: none; color: white; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #218838; }
        .links { text-align: center; margin-top: 15px; }
    </style>
</head>
<body>

<div class="register-box">
    <h2>Add New User</h2>
    <?php echo $message; ?>
    <form action="register.php" method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="members">Members</option>
                <option value="admin">Admin</option>
                <option value="finance">Finance</option>
                <option value="seceratery">Secretary</option>
            </select>
        </div>
        <button type="submit" class="btn">Register User</button>
    </form>
    <div class="links">
        <a href="Login.html">Go to Login</a>
    </div>
</div>

</body>
</html>