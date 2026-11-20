<?php
session_start();
header('Content-Type: application/json');

// Security Check: Only Admins can access
$user_role = strtolower($_SESSION['role'] ?? '');
if (!isset($_SESSION['username']) || $user_role !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}


require_once __DIR__ . '/DBConction.php';
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// 1. Total Paid Revenue (Sum of 'amount' where status is Paid)
$paid_rev_res = $conn->query("SELECT SUM(amount) as total FROM contributions WHERE status = 'Paid'");
$total_collected_revenue = floatval($paid_rev_res->fetch_assoc()['total'] ?? 0);

// 2. Breakdown of Paid Contributions vs Paid Fines (if tracked separately or combined)
// Paid Base Contributions (excluding punishment price if columns are separated)
$paid_contrib_res = $conn->query("SELECT SUM(amount - punishment_price) as total FROM contributions WHERE status = 'Paid'");
$paid_contributions = floatval($paid_contrib_res->fetch_assoc()['total'] ?? 0);

// Paid Fines / Penalties
$paid_fines_res = $conn->query("SELECT SUM(punishment_price) as total FROM contributions WHERE status = 'Paid'");
$paid_collected_fines = floatval($paid_fines_res->fetch_assoc()['total'] ?? 0);

// 3. Total Outstanding / Pending (Sum of 'amount' where status is UnPaid)
$pending_res = $conn->query("SELECT SUM(amount) as total FROM contributions WHERE status = 'UnPaid'");
$total_pending = floatval($pending_res->fetch_assoc()['total'] ?? 0);

// 4. Recent System Activity Log (Paid records)
$logs_res = $conn->query("
    SELECT username, 'Contribution Record' as category, CONCAT(month_name, ' ', contribution_year) as particulars, amount, payment_date as tx_date, status 
    FROM contributions WHERE status = 'Paid'
    ORDER BY tx_date DESC LIMIT 20
");

$activity_logs = [];
if ($logs_res) {
    while ($row = $logs_res->fetch_assoc()) {
        $activity_logs[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "full_name" => $_SESSION['full_name'] ?? $_SESSION['username'],
    "total_collected_revenue" => $total_collected_revenue,
    "total_pending" => $total_pending,
    "total_paid_contributions" => $paid_contributions,
    "total_collected_fines" => $paid_collected_fines,
    "activity_logs" => $activity_logs
]);

$conn->close();
?>