<?php
session_start();
header('Content-Type: application/json');

// 1. Session check: Ensure user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode([
        "status" => "error", 
        "message" => "Unauthorized access. No active session found."
    ]);
    exit();
}


// 2. Database connection
require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error", 
        "message" => "Database connection failed: " . $conn->connect_error
    ]);
    exit();
}

$username = $_SESSION['username'];
$selected_year = (isset($_GET['year']) && !empty($_GET['year'])) ? intval($_GET['year']) : intval(date('Y'));
$selected_month = isset($_GET['month']) ? trim($_GET['month']) : '';

// 3. Fetch distinct available years for dropdown selection
$years_query = $conn->prepare("SELECT DISTINCT contribution_year FROM contributions WHERE username = ? ORDER BY contribution_year DESC");
$years_query->bind_param("s", $username);
$years_query->execute();
$years_result = $years_query->get_result();

$available_years = [];
while ($row = $years_result->fetch_assoc()) {
    $available_years[] = intval($row['contribution_year']);
}
$years_query->close();

// Fallback to current year if no prior records exist
if (empty($available_years)) {
    $available_years[] = intval(date('Y'));
}

// 4. Query contribution records based on selected year & month
if (!empty($selected_month) && $selected_month !== 'ALL') {
    $stmt = $conn->prepare("SELECT id, month_name, contribution_year, amount, status, payment_date 
                            FROM contributions 
                            WHERE username = ? AND contribution_year = ? AND month_name = ?
                            ORDER BY FIELD(month_name, 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December') ASC");
    $stmt->bind_param("sis", $username, $selected_year, $selected_month);
} else {
    $stmt = $conn->prepare("SELECT id, month_name, contribution_year, amount, status, payment_date 
                            FROM contributions 
                            WHERE username = ? AND contribution_year = ?
                            ORDER BY FIELD(month_name, 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December') ASC");
    $stmt->bind_param("si", $username, $selected_year);
}

$stmt->execute();
$result = $stmt->get_result();

$contributions = [];
while ($row = $result->fetch_assoc()) {
    $contributions[] = $row;
}

$stmt->close();
$conn->close();

// 5. Output JSON response payload expected by contribution.html
echo json_encode([
    "status" => "success",
    "full_name" => $_SESSION['full_name'] ?? $username,
    "username" => $username,
    "selected_year" => $selected_year,
    "available_years" => $available_years,
    "contributions" => $contributions
]);
exit();
?>