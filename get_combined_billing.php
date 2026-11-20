<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/DBConction.php';

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// Get user full name
$userStmt = $conn->prepare("SELECT full_name FROM users WHERE username = ?");
$userStmt->bind_param("s", $username);
$userStmt->execute();
$userRes = $userStmt->get_result();
$fullName = ($userRes->num_rows > 0) ? $userRes->fetch_assoc()['full_name'] : $username;
$userStmt->close();

// Fetch all unpaid contributions ordered by chronological order (oldest first)
$stmt = $conn->prepare("SELECT c.*, COALESCE(p.amount, 0) AS punishment_price, p.reason AS punishment_reason FROM contributions c LEFT JOIN punishments p ON c.id = p.contribution_id WHERE c.username = ? AND c.status != 'Paid' ORDER BY c.year ASC, c.month ASC");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$unpaidItems = [];
$totalAmountDue = 0;
$currentYear = date('Y');
$currentMonthNum = date('n'); // Current numeric month (1-12)

while ($row = $result->fetch_assoc()) {
    // Map month name to number for comparison if stored as string (e.g., 'January' -> 1)
    $monthNum = date('n', strtotime($row['month'] . ' 1 2020'));
    
    // Determine if this is a previous/past month compared to current system date
    $isPastMonth = ($row['year'] < $currentYear) || ($row['year'] == $currentYear && $monthNum < $currentMonthNum);
    
    // Only apply punishment if it is strictly a previous/missing month
    $punishmentDue = $isPastMonth ? floatval($row['punishment_price']) : 0;
    $baseAmount = floatval($row['amount_required']);
    
    $itemTotal = $baseAmount + $punishmentDue;
    $totalAmountDue += $itemTotal;

    $unpaidItems[] = [
        "id" => $row['id'],
        "month" => $row['month'],
        "year" => $row['year'],
        "is_past_due" => $isPastMonth,
        "base_amount" => $baseAmount,
        "punishment" => $punishmentDue,
        "punishment_reason" => $row['punishment_reason'] ?? 'Late Fine',
        "total" => $itemTotal
    ];
}
$stmt->close();
$conn->close();

if (empty($unpaidItems)) {
    echo json_encode(["status" => "empty", "message" => "No unpaid contributions found."]);
    exit();
}

echo json_encode([
    "status" => "success",
    "full_name" => $fullName,
    "unpaid_items" => $unpaidItems,
    "grand_total" => $totalAmountDue
]);
exit();
?>