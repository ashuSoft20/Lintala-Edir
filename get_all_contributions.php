<?php
session_start();
header('Content-Type: application/json');

// Normalize role to lowercase for case-insensitive checking
$user_role = strtolower($_SESSION['role'] ?? '');

// Security Check: Only Finance officers or Admins can access (flexible role check)
if (!isset($_SESSION['username']) || !in_array($user_role, ['finance', 'admin'])) {
    echo json_encode([
        "status" => "unauthorized", 
        "message" => "Access denied. Current role: " . ($user_role ?: 'Not logged in')
    ]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

$selected_year = isset($_GET['year']) && !empty($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$selected_month = isset($_GET['month']) ? trim($_GET['month']) : '';

// Fetch available years for filter dropdown
$years_query = $conn->query("SELECT DISTINCT contribution_year FROM contributions ORDER BY contribution_year DESC");
$available_years = [];
if ($years_query) {
    while ($row = $years_query->fetch_assoc()) {
        $available_years[] = $row['contribution_year'];
    }
}
if (empty($available_years)) {
    $available_years[] = intval(date('Y'));
}

// Fetch all contributions joined with user full_name
if (!empty($selected_month) && $selected_month !== 'ALL') {
    $stmt = $conn->prepare("
        SELECT c.id, c.username, u.full_name, c.month_name, c.contribution_year, c.amount, c.status, c.payment_date 
        FROM contributions c
        LEFT JOIN users u ON c.username = u.username
        WHERE c.contribution_year = ? AND c.month_name = ? 
        ORDER BY c.id DESC
    ");
    $stmt->bind_param("is", $selected_year, $selected_month);
} else {
    $stmt = $conn->prepare("
        SELECT c.id, c.username, u.full_name, c.month_name, c.contribution_year, c.amount, c.status, c.payment_date 
        FROM contributions c
        LEFT JOIN users u ON c.username = u.username
        WHERE c.contribution_year = ? 
        ORDER BY c.id DESC
    ");
    $stmt->bind_param("i", $selected_year);
}

$stmt->execute();
$result = $stmt->get_result();

$contributions = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = $row['full_name'] ?? $row['username']; // Fallback to username if full_name is null
    $contributions[] = $row;
}

echo json_encode([
    "status" => "success",
    "full_name" => $_SESSION['full_name'] ?? $_SESSION['username'],
    "selected_year" => $selected_year,
    "selected_month" => $selected_month,
    "available_years" => $available_years,
    "contributions" => $contributions
]);

$stmt->close();
$conn->close();
?>